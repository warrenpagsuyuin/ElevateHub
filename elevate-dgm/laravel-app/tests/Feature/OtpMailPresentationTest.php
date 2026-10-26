<?php

namespace Tests\Feature;

use App\Mail\OtpMail;
use DOMDocument;
use DOMXPath;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class OtpMailPresentationTest extends TestCase
{
    public function test_real_multipart_message_contains_cid_logo_and_matching_copy(): void
    {
        config(['mail.default' => 'array']);
        $sent = Mail::to('preview@example.test')->send(new OtpMail('123456', '2026-10-26T09:32:04Z'));
        $message = $sent->getSymfonySentMessage()->getOriginalMessage();
        $html = $message->getHtmlBody();
        $text = $message->getTextBody();
        $this->assertSame('Your Elevate sign-in code', $message->getSubject());
        $this->assertSame('preview@example.test', $message->getTo()[0]->getAddress());
        $this->assertStringContainsString('multipart/alternative', $message->toString());
        $this->assertStringContainsString('multipart/related', $message->toString());
        $this->assertLessThan(30000, strlen($html));
        $this->assertStringContainsString('src="cid:', $html);
        $this->assertCount(1, $message->getAttachments());
        $this->assertSame('inline', $message->getAttachments()[0]->getDisposition());
        $this->assertSame('image/png', $message->getAttachments()[0]->getMediaType().'/'.$message->getAttachments()[0]->getMediaSubtype());
        $this->assertStringContainsString('<strong style="font-weight:700;">26 Oct 2026, 09:32:04 UTC</strong>', $html);
        foreach (['Your sign-in code', 'Hello,', 'Enter this one-time code to sign in to Elevate.', '123456', 'This code expires in 5 minutes.', '26 Oct 2026, 09:32:04 UTC', 'Never share this code.', 'Elevate staff will never ask for it.', "Didn't request this? You can ignore this email. If you keep receiving these, contact your administrator."] as $copy) {
            $this->assertStringContainsString($copy, html_entity_decode(strip_tags($html)));
            $this->assertStringContainsString($copy, $text);
        }
        $dom = new DOMDocument;
        @$dom->loadHTML($html);
        $xpath = new DOMXPath($dom);
        $this->assertSame(1, $xpath->query('//h1')->length);
        $this->assertSame(1, $xpath->query('//img')->length);
        $this->assertSame('Elevate', $xpath->query('//img')->item(0)->getAttribute('alt'));
        foreach (['width', 'height'] as $attribute) {
            $this->assertNotEmpty($xpath->query('//img')->item(0)->getAttribute($attribute));
        }
        $preheader = $xpath->query('//body/div')->item(0);
        $this->assertSame('Use this one-time code to sign in. It expires in 5 minutes.', $preheader->textContent);
        $this->assertStringNotContainsString('123456', $preheader->textContent);
        $digits = $xpath->query('//p[@class="code-digits"]')->item(0);
        $this->assertSame('123456', $digits->textContent);
        $this->assertSame(1, $digits->childNodes->length);
        $this->assertSame(0, $xpath->query('//table[not(@role="presentation")]')->length);
        $this->assertSame(0, $xpath->query('//body//*[not(@style)]')->length);
        $this->assertSame(0, $xpath->query('//script|//link|//a')->length);
        $this->assertStringNotContainsString('var(', $html);
        $this->assertDoesNotMatchRegularExpression('/(?:src|href)=["\x27](?:https?:|data:)/i', $html);
        $css = $xpath->query('//style')->item(0)->textContent;
        $this->assertStringNotContainsString('@import', $css);
        $this->assertStringNotContainsString('@font-face', $css);
        $this->assertSame('', trim(preg_replace('/@media[^{}]+\{(?:[^{}]|\{[^{}]*\})*\}/s', '', $css)));
        $logo = getimagesize(public_path('images/email/elevate-logo.png'));
        $this->assertSame([300, 56], [$logo[0], $logo[1]]);
        $this->assertLessThan(20000, filesize(public_path('images/email/elevate-logo.png')));
        fwrite(STDOUT, '\nOTP HTML: '.strlen($html).' bytes; CID PNG: '.filesize(public_path('images/email/elevate-logo.png'))." bytes\n");
    }

    public function test_preview_is_local_only_and_uses_a_dummy_code(): void
    {
        $this->get('https://localhost/dev/mail/sign-in-code')->assertNotFound();
        $this->app->instance('env', 'production');
        $this->get('https://localhost/dev/mail/sign-in-code')->assertNotFound();
        $this->app->instance('env', 'local');
        Mail::fake();
        $this->get('/dev/mail/sign-in-code')->assertOk()->assertSee('123456')->assertSee('/images/email/elevate-logo.png', false)->assertDontSee('src="cid:', false);
        Mail::assertNothingSent();
    }

    public function test_palette_contrast_is_at_least_four_point_five(): void
    {
        $source = file_get_contents(resource_path('views/emails/otp.blade.php'));
        preg_match_all("/'(\w+)' => '(#[A-Fa-f0-9]{6})'/", $source, $matches, PREG_SET_ORDER);
        $colors = array_column($matches, 2, 1);
        $luminance = static function (string $hex): float {
            $rgb = array_map(static function ($channel) {
                $s = hexdec($channel) / 255;

                return $s <= 0.04045 ? $s / 12.92 : (($s + 0.055) / 1.055) ** 2.4;
            }, str_split(substr($hex, 1), 2));

            return $rgb[0] * 0.2126 + $rgb[1] * 0.7152 + $rgb[2] * 0.0722;
        };
        foreach ([['text', 'white'], ['light', 'charcoal'], ['muted', 'charcoal'], ['secondary', 'white'], ['text', 'outer'], ['secondary', 'outer'], ['light', 'darkNote'], ['muted', 'darkOuter'], ['white', 'charcoal']] as [$foreground, $background]) {
            $a = $luminance($colors[$foreground]);
            $b = $luminance($colors[$background]);
            $ratio = (max($a, $b) + 0.05) / (min($a, $b) + 0.05);
            $this->assertGreaterThanOrEqual(4.5, $ratio, "$foreground on $background");
            fwrite(STDOUT, sprintf("%s on %s: %.2f:1\n", $colors[$foreground], $colors[$background], $ratio));
        }
    }
}
