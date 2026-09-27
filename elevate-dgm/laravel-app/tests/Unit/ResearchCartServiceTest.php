<?php

namespace Tests\Unit;

use App\Services\ResearchCartService;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

class ResearchCartServiceTest extends TestCase
{
    private function samples(): array
    {
        $samples = [];
        for ($id = 1; $id <= 206; $id++) {
            $label = $id <= 112 ? 0 : ($id <= 175 ? 1 : ($id <= 200 ? 2 : null));
            $samples[] = ['member_id' => $id, 'label' => $label === null ? null : ResearchCartService::LABELS[$label],
                'features' => ['active_disciples' => $label === null ? null : $label * 10 + $id % 3, 'name' => 'Forbidden predictor', 'member_id' => $id]];
        }
        return $samples;
    }

    public function test_split_is_stratified_deterministic_disjoint_and_excludes_clarification(): void
    {
        $engine = new ResearchCartService;
        $split = $engine->split($this->samples());
        $this->assertSame($split, $engine->split(array_reverse($this->samples())));
        $this->assertCount(160, $split['train']);
        $this->assertCount(40, $split['test']);
        $this->assertSame([22,13,5], array_column($split['summary']['classes'], 'testing'));
        $this->assertSame([], array_intersect($split['summary']['train_member_ids'], $split['summary']['test_member_ids']));
        $this->assertSame([201,202,203,204,205,206], $split['summary']['excluded_member_ids']);
        $this->assertNotSame($split['summary']['test_member_ids'], $engine->split($this->samples(), 43)['summary']['test_member_ids']);
        $this->assertSame(ResearchCartService::FEATURE_NAMES, array_keys($split['train'][0]['features']));
    }

    public function test_metrics_include_failures_and_correct_macro_weighted_values(): void
    {
        [$a,$b,$c] = ResearchCartService::LABELS;
        $m = (new ResearchCartService)->metrics([
            ['actual'=>$a,'predicted'=>$a], ['actual'=>$a,'predicted'=>$b], ['actual'=>$a,'predicted'=>null],
            ['actual'=>$b,'predicted'=>$b], ['actual'=>$c,'predicted'=>$b],
        ]);
        $this->assertSame(5, $m['sample_count']);
        $this->assertSame(1, $m['failed_count']);
        $this->assertEqualsWithDelta(.4, $m['accuracy'], 1e-10);
        $this->assertSame(3, $m['per_class'][$a]['support']);
        $this->assertEqualsWithDelta(1, $m['per_class'][$a]['precision'], 1e-10);
        $this->assertEqualsWithDelta(1/3, $m['per_class'][$a]['recall'], 1e-10);
        $this->assertEqualsWithDelta(.5, $m['per_class'][$a]['f1'], 1e-10);
        $this->assertEqualsWithDelta(4/9, $m['macro_precision'], 1e-10);
        $this->assertEqualsWithDelta(4/9, $m['macro_recall'], 1e-10);
        $this->assertEqualsWithDelta(1/3, $m['macro_f1'], 1e-10);
        $this->assertEqualsWithDelta(2/3, $m['weighted_precision'], 1e-10);
        $this->assertEqualsWithDelta(.4, $m['weighted_recall'], 1e-10);
        $this->assertEqualsWithDelta(.4, $m['weighted_f1'], 1e-10);
        $this->assertSame(0.0, $m['per_class'][$c]['precision']);
        $this->assertCount(3, $m['confusion_matrix']);
    }

    public function test_cv_imputation_and_selection_never_use_heldout_and_xai_traverses_real_tree(): void
    {
        $engine = new ResearchCartService;
        $split = $engine->split($this->samples());
        $result = $engine->evaluate($split['train'], $split['test']);
        $this->assertSame(5, $result['cv']['fold_count']);
        $this->assertGreaterThanOrEqual(350, count($result['cv']['candidates']));
        $this->assertCount(350, $result['cv']['pruning_paths']);
        $path = $result['final_training_pruning_path'];
        $this->assertSame(1, end($path)['leaf_count']);
        for ($i=1; $i<count($path); $i++) {
            $this->assertGreaterThanOrEqual($path[$i-1]['ccp_alpha'], $path[$i]['ccp_alpha']);
            $this->assertLessThan($path[$i-1]['leaf_count'], $path[$i]['leaf_count']);
        }
        $validationIds = [];
        foreach ($result['cv']['folds'] as $fold) {
            $this->assertSame([], array_intersect($fold['training_member_ids'], $fold['validation_member_ids']));
            $this->assertSame([], array_intersect($fold['training_member_ids'], $result['heldout_member_ids']));
            $this->assertSame([], array_intersect($fold['validation_member_ids'], $result['heldout_member_ids']));
            array_push($validationIds, ...$fold['validation_member_ids']);
        }
        $this->assertCount(160, array_unique($validationIds));
        $changedTest = $split['test'];
        foreach ($changedTest as &$sample) {
            $sample['features']['active_disciples'] = 999999;
            $sample['features']['weekly_dgroups_led'] = 999999;
            $sample['label'] = ResearchCartService::LABELS[2];
        }
        unset($sample);
        $changed = $engine->evaluate($split['train'], $changedTest);
        foreach (['tree','imputation','selected_parameters','cv','training_metrics'] as $key) {
            $this->assertSame($result[$key], $changed[$key]);
        }
        $this->assertSame(0.0, $result['imputation']['weekly_dgroups_led']);
        $prediction = $engine->predict($result['tree'], ['active_disciples'=>null], $result['imputation']);
        $node = $result['tree'];
        foreach ($prediction['decision_path'] as $step) {
            $this->assertSame($node['node_id'], $step['node_id']);
            if ($step['feature'] !== 'leaf') {
                $this->assertSame($node['feature'], $step['feature']);
                $this->assertEquals($node['threshold'], $step['threshold']);
                $node = $node[$step['branch_taken']];
            } else {
                $this->assertSame($node['prediction'], $prediction['classification']);
            }
        }
        $this->assertGreaterThan(.9, $result['test_metrics']['accuracy']);
        $this->assertSame(ResearchCartService::LABELS[0], $result['baseline']['majority_class']);
        $this->assertEqualsWithDelta(22/40, $result['baseline']['accuracy'], 1e-10);
    }

    public function test_cost_complexity_prunes_an_expensive_split_to_a_leaf(): void
    {
        $engine = new ResearchCartService;
        $split = $engine->split($this->samples());
        $result = $engine->evaluate($split['train'], $split['test']);
        $prune = new \ReflectionMethod($engine, 'pruneTree');
        [$tree] = $prune->invoke($engine, $result['tree'], 1.0, count($split['train']));
        $this->assertSame('leaf', $tree['type']);
        $this->assertArrayNotHasKey('left', $tree);
        $this->assertSame('Final CART Leaf', $engine->predict($tree, [])['decision_path'][0]['label']);
    }

    public function test_overlapping_train_test_is_rejected(): void
    {
        $engine = new ResearchCartService;
        $split = $engine->split($this->samples());
        $this->expectException(InvalidArgumentException::class);
        $engine->evaluate($split['train'], [$split['train'][0]]);
    }

    public function test_balanced_weights_affect_gini_and_leaf_probabilities_without_changing_sample_counts(): void
    {
        $engine = new ResearchCartService;
        $samples = [];
        foreach ([6, 2, 2] as $class => $count) for ($i=0; $i<$count; $i++) {
            $samples[] = ['member_id'=>count($samples)+1, 'label'=>ResearchCartService::LABELS[$class], 'features'=>array_fill_keys(ResearchCartService::FEATURE_NAMES, 0.0)];
        }
        $fit = new \ReflectionMethod($engine, 'fitTree');
        $p = ['max_depth'=>null,'min_samples_split'=>2,'min_samples_leaf'=>1,'ccp_alpha'=>0.0];
        $plain = $fit->invoke($engine, $samples, $p + ['class_weight'=>null]);
        $balanced = $fit->invoke($engine, $samples, $p + ['class_weight'=>'balanced']);
        $this->assertSame(10, $balanced['samples']);
        $this->assertSame([6,2,2], array_values($balanced['distribution']));
        $this->assertEqualsWithDelta(.6, $plain['confidence'], 1e-12);
        $this->assertEqualsWithDelta(1/3, $balanced['confidence'], 1e-12);
        $this->assertEqualsWithDelta(2/3, $balanced['gini'], 1e-12);
        foreach ($balanced['weighted_distribution'] as $count) $this->assertEqualsWithDelta(10/3, $count, 1e-12);
    }

    public function test_weakest_link_path_has_exact_hand_calculated_alphas(): void
    {
        $leaf = fn ($samples) => ['type'=>'leaf','samples'=>$samples,'gini'=>0.0];
        $tree = ['type'=>'split','samples'=>8,'gini'=>.46875,
            'left'=>['type'=>'split','samples'=>4,'gini'=>.375,'left'=>$leaf(3),'right'=>$leaf(1)], 'right'=>$leaf(4)];
        $path = (new ResearchCartService)->pruningPath($tree);
        $this->assertSame([3,2,1], array_column($path,'leaf_count'));
        $this->assertSame([0.0,.1875,.28125], array_column($path,'ccp_alpha'));
        $this->assertSame([0.0,.1875,.46875], array_column($path,'impurity'));
    }
}
