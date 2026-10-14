<?php

namespace App\Services;

use App\Models\Seeker;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use RuntimeException;

class GoogleSheetSeekerSync
{
    public function sync(): int
    {
        $rows = $this->fetchRows();
        $synced = 0;

        foreach ($rows as $row) {
            $name = $this->nameFromRow($row);
            $email = strtolower($this->valueFromRow($row, ['email', 'email_address', 'email_add', 'email_ad']));
            $sourceKey = $this->sourceKeyFromRow($row);

            if ($name === '' || ! filter_var($email, FILTER_VALIDATE_EMAIL)) {
                continue;
            }

            $assignedDistrict = $this->assignedDistrictFromRow($row);
            $assignedDistrict = $assignedDistrict === '' ? 'Unassigned' : $assignedDistrict;

            $values = [
                'source_key' => $sourceKey,
                'name' => $name,
                'last_name' => $this->valueFromRow($row, ['last_name', 'last_nam', 'lastname']) ?: null,
                'first_name' => $this->valueFromRow($row, ['first_name', 'first_nam', 'firstname']) ?: null,
                'email' => $email,
                'gender' => $this->valueFromRow($row, ['gender', 'sex']) ?: null,
                'contact_number' => $this->valueFromRow($row, ['contact_number', 'contact', 'phone', 'mobile', 'mobile_number']) ?: null,
                'facebook_link' => $this->facebookLinkFromRow($row),
                'birthdate' => $this->valueFromRow($row, ['birthdate', 'birth_date', 'date_of_birth']) ?: null,
                'age' => $this->valueFromRow($row, ['age']) ?: null,
                'school' => $this->valueFromRow($row, ['school', 'campus_school', 'campus', 'campus_or_school']) ?: null,
                'dgroup_leader_name' => $this->valueFromRow($row, ['dgroup_leader_name', 'name_of_dleader', 'name_of_dgroup_leader', 'dleader', 'dgroup_leader', 'assigned_dleader']) ?: null,
                'area' => $this->valueFromRow($row, ['area']) ?: null,
                'active_disciples' => $this->valueFromRow($row, ['active_disciples', 'no_of_active_disciples', 'number_of_active_disciples']) ?: null,
                'year_joined' => $this->valueFromRow($row, ['year_joined']) ?: null,
                'ministry_involvement' => $this->valueFromRow($row, ['ministry_involvement']) ?: null,
                'weekly_dgroups_led' => $this->valueFromRow($row, ['weekly_dgroups_led', 'how_many_dgroups_do_you_lead_each_week']) ?: null,
                'generation' => $this->valueFromRow($row, ['generation']) ?: null,
                'refresh_date' => $this->valueFromRow($row, ['refresh_date']) ?: null,
                'year' => $this->valueFromRow($row, ['year']) ?: null,
                'month' => $this->valueFromRow($row, ['month']) ?: null,
                'assigned_district' => $assignedDistrict === 'Unassigned' ? null : $assignedDistrict,
                'assigned_satellite_slug' => $assignedDistrict === 'Unassigned' ? null : Str::slug($assignedDistrict),
                'status' => Seeker::normalizeStatus($this->statusFromRow($row)),
            ];

            $seeker = $sourceKey === ''
                ? null
                : Seeker::where('source_key', $sourceKey)->first();

            if ($seeker === null && $sourceKey !== '') {
                $seeker = Seeker::whereNull('source_key')
                    ->where('email', $email)
                    ->first();
            }

            DB::transaction(function () use ($seeker, $values): void {
                if ($seeker) {
                    $seeker = Seeker::whereKey($seeker->id)->lockForUpdate()->firstOrFail();
                    // Archived rows are historical records; sync cannot silently restore or reassign them.
                    if ($seeker->archived_at) {
                        return;
                    }
                    $status = $values['status'];
                    unset($values['status']);
                    $seeker->update($values);
                    // An unchanged final status may be an explicitly restored, stay-active record.
                    if ($seeker->status !== $status) {
                        app(SeekerArchiveService::class)->changeStatus($seeker, $status, auth()->user());
                    }
                } else {
                    $status = $values['status'];
                    $values['status'] = Seeker::STATUS_PENDING;
                    $seeker = Seeker::create($values);
                    app(SeekerArchiveService::class)->changeStatus($seeker, $status, auth()->user());
                }
            }, 3);

            $synced++;
        }

        return $synced;
    }

    /**
     * @param  array<string, string|null>  $row
     */
    private function nameFromRow(array $row): string
    {
        $name = $this->valueFromRow($row, ['name', 'full_name', 'seeker_name']);

        if ($name !== '') {
            return $name;
        }

        $firstName = $this->valueFromRow($row, ['first_name', 'first_nam', 'firstname']);
        $lastName = $this->valueFromRow($row, ['last_name', 'last_nam', 'lastname']);

        return trim($firstName.' '.$lastName);
    }

    /**
     * @param  array<string, string|null>  $row
     */
    private function statusFromRow(array $row): string
    {
        return $this->valueFromRow($row, [
            'dm_remarks_for_closed_status',
            'final_placement_status',
            'status',
            'seeker_status',
            'dm_status',
            'dm_result',
            'dm_remarks',
            'dm_rema',
            'final_placement',
            'final_plac',
        ]) ?: Seeker::STATUS_PENDING;
    }

    /**
     * @param  array<string, string|null>  $row
     */
    private function assignedDistrictFromRow(array $row): string
    {
        $assignedDistrict = $this->valueFromRow($row, [
            'assigned_district',
            'assigned_satellite',
            'satellite',
            'district',
            'landmark_area',
        ]);

        if ($assignedDistrict !== '') {
            return $assignedDistrict;
        }

        $countrySatellite = $this->valueFromRow($row, ['country_satellite']);

        if (preg_match('/\bCCF\s+(.+)$/i', $countrySatellite, $matches) === 1) {
            return trim($matches[1]);
        }

        return '';
    }

    /**
     * @param  array<string, string|null>  $row
     */
    private function facebookLinkFromRow(array $row): ?string
    {
        $facebook = $this->valueFromRow($row, ['facebook_link', 'facebook', 'facebook_url', 'facebook_name_link']);

        if ($facebook === '') {
            return null;
        }

        return Str::startsWith($facebook, ['http://', 'https://'])
            ? $facebook
            : 'https://'.$facebook;
    }

    /**
     * @param  array<string, string|null>  $row
     */
    private function sourceKeyFromRow(array $row): string
    {
        $sourceRowNumber = $this->valueFromRow($row, ['__source_row_number']);

        return $sourceRowNumber === ''
            ? ''
            : 'google-sheet-row-'.$sourceRowNumber;
    }

    /**
     * @param  array<string, string|null>  $row
     * @param  array<int, string>  $keys
     */
    private function valueFromRow(array $row, array $keys): string
    {
        foreach ($keys as $key) {
            $value = trim((string) ($row[$key] ?? ''));

            if ($value !== '') {
                return $value;
            }
        }

        return '';
    }

    /**
     * @return array<int, array<string, string|null>>
     */
    private function fetchRows(): array
    {
        if ($this->hasCsvUrlConfig()) {
            $csv = $this->fetchCsv();

            return $this->parseCsv($csv);
        }

        if ($this->hasServiceAccountConfig()) {
            return $this->fetchRowsFromGoogleSheetsApi();
        }

        $csv = $this->fetchCsv();

        return $this->parseCsv($csv);
    }

    private function fetchCsv(): string
    {
        $url = $this->sheetUrl();

        if ($url === null) {
            throw new RuntimeException('Set GOOGLE_SHEET_CSV_URL or GOOGLE_SHEET_ID before syncing seekers.');
        }

        $response = Http::timeout(15)->get($url);

        if (! $response->successful()) {
            throw new RuntimeException('Google Sheets sync failed. Check that the sheet is published or shared for CSV export.');
        }

        return $response->body();
    }

    /**
     * @return array<int, array<string, string|null>>
     */
    private function fetchRowsFromGoogleSheetsApi(): array
    {
        $spreadsheetId = trim((string) config('services.google_sheets.seekers_sheet_id'));
        $accessToken = $this->serviceAccountAccessToken();

        try {
            $range = $this->sheetRange($spreadsheetId, $accessToken);
        } catch (RuntimeException $exception) {
            if (str_contains($exception->getMessage(), 'Office file')) {
                return $this->fetchRowsFromOfficeFile($spreadsheetId, $accessToken);
            }

            throw $exception;
        }

        return $this->fetchRowsFromSpreadsheet($spreadsheetId, $range, $accessToken);
    }

    /**
     * @return array<int, array<string, string|null>>
     */
    private function fetchRowsFromSpreadsheet(string $spreadsheetId, string $range, string $accessToken): array
    {
        $encodedRange = rawurlencode($range);

        $response = Http::withToken($accessToken)
            ->timeout(20)
            ->get("https://sheets.googleapis.com/v4/spreadsheets/{$spreadsheetId}/values/{$encodedRange}", [
                'majorDimension' => 'ROWS',
            ]);

        if (! $response->successful()) {
            throw new RuntimeException('Google Sheets API read failed: '.$this->googleErrorMessage($response->status(), $response->json()));
        }

        return $this->rowsFromValues($response->json('values', []));
    }

    /**
     * @return array<int, array<string, string|null>>
     */
    private function fetchRowsFromOfficeFile(string $fileId, string $accessToken): array
    {
        try {
            return $this->fetchRowsFromAuthenticatedCsvExport($fileId, $accessToken);
        } catch (RuntimeException) {
            //
        }

        try {
            $response = Http::withToken($accessToken)
                ->withHeaders([
                    'Cache-Control' => 'no-cache',
                    'Pragma' => 'no-cache',
                ])
                ->timeout(30)
                ->get("https://www.googleapis.com/drive/v3/files/{$fileId}", [
                    'alt' => 'media',
                    '_' => now()->timestamp,
                ]);

            if (! $response->successful()) {
                throw new RuntimeException('Google Drive could not download the Office file: '.$this->googleErrorMessage($response->status(), $response->json()));
            }

            return $this->rowsFromValues($this->valuesFromXlsx($response->body()));
        } catch (RuntimeException $downloadException) {
            try {
                return $this->fetchRowsFromConvertedOfficeFile($fileId, $accessToken);
            } catch (RuntimeException $conversionException) {
                throw new RuntimeException(
                    $downloadException->getMessage().' '.$conversionException->getMessage(),
                    previous: $conversionException
                );
            }
        }
    }

    /**
     * @return array<int, array<string, string|null>>
     */
    private function fetchRowsFromAuthenticatedCsvExport(string $fileId, string $accessToken): array
    {
        $gid = trim((string) config('services.google_sheets.seekers_gid', '0'));
        $response = Http::withToken($accessToken)
            ->withHeaders([
                'Cache-Control' => 'no-cache',
                'Pragma' => 'no-cache',
            ])
            ->timeout(30)
            ->get("https://docs.google.com/spreadsheets/d/{$fileId}/export", [
                'format' => 'csv',
                'gid' => $gid === '' ? '0' : $gid,
                '_' => now()->timestamp,
            ]);

        $body = $response->body();

        if (! $response->successful() || str_starts_with(ltrim($body), '<')) {
            throw new RuntimeException('Google Docs CSV export failed for the Office file.');
        }

        return $this->parseCsv($body);
    }

    /**
     * @return array<int, array<string, string|null>>
     */
    private function fetchRowsFromConvertedOfficeFile(string $fileId, string $accessToken): array
    {
        $convertedSpreadsheetId = $this->convertOfficeFileToTemporarySheet($fileId, $accessToken);

        try {
            $range = $this->sheetRange($convertedSpreadsheetId, $accessToken);

            return $this->fetchRowsFromSpreadsheet($convertedSpreadsheetId, $range, $accessToken);
        } finally {
            $this->deleteTemporaryDriveFile($convertedSpreadsheetId, $accessToken);
        }
    }

    private function convertOfficeFileToTemporarySheet(string $fileId, string $accessToken): string
    {
        $response = Http::withToken($accessToken)
            ->timeout(30)
            ->post("https://www.googleapis.com/drive/v3/files/{$fileId}/copy", [
                'name' => 'Elevate DGM temporary seeker sync '.now()->format('Y-m-d H:i:s'),
                'mimeType' => 'application/vnd.google-apps.spreadsheet',
            ]);

        if (! $response->successful() || ! is_string($response->json('id'))) {
            throw new RuntimeException('Google Drive could not convert the Office file: '.$this->googleErrorMessage($response->status(), $response->json()));
        }

        return $response->json('id');
    }

    private function deleteTemporaryDriveFile(string $fileId, string $accessToken): void
    {
        Http::withToken($accessToken)
            ->timeout(15)
            ->delete("https://www.googleapis.com/drive/v3/files/{$fileId}");
    }

    /**
     * @return array<int, array<int, string|null>>
     */
    private function valuesFromXlsx(string $xlsx): array
    {
        $entries = $this->zipEntries($xlsx);
        $sharedStrings = $this->sharedStrings($entries['xl/sharedStrings.xml'] ?? null);
        $worksheetPath = $this->firstWorksheetPath($entries);
        $worksheetXml = $entries[$worksheetPath] ?? null;

        if ($worksheetXml === null) {
            throw new RuntimeException('The Office file does not contain a readable worksheet.');
        }

        $worksheet = simplexml_load_string($worksheetXml);

        if ($worksheet === false || ! isset($worksheet->sheetData)) {
            throw new RuntimeException('The Office worksheet XML could not be parsed.');
        }

        $values = [];

        foreach ($worksheet->sheetData->row as $row) {
            $rowValues = [];

            foreach ($row->c as $cell) {
                $cellReference = (string) ($cell['r'] ?? '');
                $columnIndex = $this->columnIndexFromCellReference($cellReference);
                $rowValues[$columnIndex] = $this->cellValue($cell, $sharedStrings);
            }

            if ($rowValues === []) {
                continue;
            }

            ksort($rowValues);
            $lastColumnIndex = max(array_keys($rowValues));
            $normalizedRow = [];

            for ($columnIndex = 0; $columnIndex <= $lastColumnIndex; $columnIndex++) {
                $normalizedRow[] = $rowValues[$columnIndex] ?? null;
            }

            $values[] = $normalizedRow;
        }

        return $values;
    }

    /**
     * @param  array<string, string>  $entries
     */
    private function firstWorksheetPath(array $entries): string
    {
        $workbookXml = $entries['xl/workbook.xml'] ?? null;
        $relsXml = $entries['xl/_rels/workbook.xml.rels'] ?? null;

        if ($workbookXml !== null && $relsXml !== null) {
            $workbook = simplexml_load_string($workbookXml);
            $rels = simplexml_load_string($relsXml);

            if ($workbook !== false && $rels !== false && isset($workbook->sheets->sheet[0])) {
                $sheet = $workbook->sheets->sheet[0];
                $attributes = $sheet->attributes('http://schemas.openxmlformats.org/officeDocument/2006/relationships');
                $relationshipId = (string) ($attributes['id'] ?? '');

                foreach ($rels->Relationship as $relationship) {
                    if ((string) ($relationship['Id'] ?? '') === $relationshipId) {
                        $target = ltrim((string) ($relationship['Target'] ?? ''), '/');

                        return str_starts_with($target, 'xl/')
                            ? $target
                            : 'xl/'.$target;
                    }
                }
            }
        }

        foreach (array_keys($entries) as $name) {
            if (str_starts_with($name, 'xl/worksheets/') && str_ends_with($name, '.xml')) {
                return $name;
            }
        }

        throw new RuntimeException('The Office file does not contain worksheet XML.');
    }

    /**
     * @return array<int, string>
     */
    private function sharedStrings(?string $xml): array
    {
        if ($xml === null) {
            return [];
        }

        $sharedStrings = simplexml_load_string($xml);

        if ($sharedStrings === false) {
            return [];
        }

        $values = [];

        foreach ($sharedStrings->si as $item) {
            if (isset($item->t)) {
                $values[] = (string) $item->t;

                continue;
            }

            $text = '';

            foreach ($item->r as $run) {
                $text .= (string) ($run->t ?? '');
            }

            $values[] = $text;
        }

        return $values;
    }

    /**
     * @param  array<int, string>  $sharedStrings
     */
    private function cellValue(\SimpleXMLElement $cell, array $sharedStrings): ?string
    {
        $type = (string) ($cell['t'] ?? '');

        if ($type === 'inlineStr') {
            return (string) ($cell->is->t ?? '');
        }

        $value = (string) ($cell->v ?? '');

        if ($type === 's') {
            return $sharedStrings[(int) $value] ?? '';
        }

        return $value;
    }

    private function columnIndexFromCellReference(string $reference): int
    {
        preg_match('/^[A-Z]+/', $reference, $matches);
        $letters = $matches[0] ?? 'A';
        $index = 0;

        foreach (str_split($letters) as $letter) {
            $index = ($index * 26) + (ord($letter) - 64);
        }

        return max(0, $index - 1);
    }

    /**
     * @return array<string, string>
     */
    private function zipEntries(string $zip): array
    {
        $endOfCentralDirectory = strrpos($zip, "PK\x05\x06");

        if ($endOfCentralDirectory === false) {
            throw new RuntimeException('The Office file is not a valid XLSX zip archive.');
        }

        $entryCount = $this->littleEndianUInt16($zip, $endOfCentralDirectory + 10);
        $centralDirectoryOffset = $this->littleEndianUInt32($zip, $endOfCentralDirectory + 16);
        $offset = $centralDirectoryOffset;
        $entries = [];

        for ($i = 0; $i < $entryCount; $i++) {
            if (substr($zip, $offset, 4) !== "PK\x01\x02") {
                break;
            }

            $compressionMethod = $this->littleEndianUInt16($zip, $offset + 10);
            $compressedSize = $this->littleEndianUInt32($zip, $offset + 20);
            $fileNameLength = $this->littleEndianUInt16($zip, $offset + 28);
            $extraLength = $this->littleEndianUInt16($zip, $offset + 30);
            $commentLength = $this->littleEndianUInt16($zip, $offset + 32);
            $localHeaderOffset = $this->littleEndianUInt32($zip, $offset + 42);
            $fileName = substr($zip, $offset + 46, $fileNameLength);

            $localFileNameLength = $this->littleEndianUInt16($zip, $localHeaderOffset + 26);
            $localExtraLength = $this->littleEndianUInt16($zip, $localHeaderOffset + 28);
            $dataOffset = $localHeaderOffset + 30 + $localFileNameLength + $localExtraLength;
            $compressedData = substr($zip, $dataOffset, $compressedSize);

            $entries[$fileName] = match ($compressionMethod) {
                0 => $compressedData,
                8 => gzinflate($compressedData),
                default => throw new RuntimeException("Unsupported XLSX compression method {$compressionMethod}."),
            };

            $offset += 46 + $fileNameLength + $extraLength + $commentLength;
        }

        return $entries;
    }

    private function littleEndianUInt16(string $bytes, int $offset): int
    {
        return unpack('v', substr($bytes, $offset, 2))[1];
    }

    private function littleEndianUInt32(string $bytes, int $offset): int
    {
        return unpack('V', substr($bytes, $offset, 4))[1];
    }

    private function serviceAccountAccessToken(): string
    {
        $testingAccessToken = config('services.google_sheets.testing_access_token');

        if (app()->environment('testing') && is_string($testingAccessToken) && trim($testingAccessToken) !== '') {
            return trim($testingAccessToken);
        }

        $path = trim((string) config('services.google_sheets.service_account_json'));

        if ($path === '' || ! is_readable($path)) {
            throw new RuntimeException('Set GOOGLE_SHEET_SERVICE_ACCOUNT_JSON to a readable service account JSON file.');
        }

        $credentials = json_decode((string) file_get_contents($path), true);

        if (! is_array($credentials) || empty($credentials['client_email']) || empty($credentials['private_key'])) {
            throw new RuntimeException('The Google service account JSON is missing client_email or private_key.');
        }

        $now = time();
        $assertion = $this->signedJwt(
            [
                'alg' => 'RS256',
                'typ' => 'JWT',
            ],
            [
                'iss' => $credentials['client_email'],
                'scope' => 'https://www.googleapis.com/auth/spreadsheets.readonly https://www.googleapis.com/auth/drive',
                'aud' => 'https://oauth2.googleapis.com/token',
                'iat' => $now,
                'exp' => $now + 3600,
            ],
            $credentials['private_key']
        );

        $response = Http::asForm()
            ->timeout(20)
            ->post('https://oauth2.googleapis.com/token', [
                'grant_type' => 'urn:ietf:params:oauth:grant-type:jwt-bearer',
                'assertion' => $assertion,
            ]);

        if (! $response->successful() || ! is_string($response->json('access_token'))) {
            throw new RuntimeException('Google service account authentication failed: '.$this->googleErrorMessage($response->status(), $response->json()));
        }

        return $response->json('access_token');
    }

    private function signedJwt(array $headers, array $claims, string $privateKey): string
    {
        $unsigned = $this->base64UrlEncode(json_encode($headers, JSON_THROW_ON_ERROR))
            .'.'
            .$this->base64UrlEncode(json_encode($claims, JSON_THROW_ON_ERROR));

        $signature = '';
        $signed = openssl_sign($unsigned, $signature, $privateKey, OPENSSL_ALGO_SHA256);

        if ($signed !== true) {
            throw new RuntimeException('Unable to sign the Google service account JWT.');
        }

        return $unsigned.'.'.$this->base64UrlEncode($signature);
    }

    private function base64UrlEncode(string $value): string
    {
        return rtrim(strtr(base64_encode($value), '+/', '-_'), '=');
    }

    private function sheetRange(string $spreadsheetId, string $accessToken): string
    {
        $configuredRange = trim((string) config('services.google_sheets.seekers_range'));

        if ($configuredRange !== '') {
            return $configuredRange;
        }

        $gid = trim((string) config('services.google_sheets.seekers_gid', '0'));

        if ($gid === '') {
            return 'A:ZZ';
        }

        $response = Http::withToken($accessToken)
            ->timeout(20)
            ->get("https://sheets.googleapis.com/v4/spreadsheets/{$spreadsheetId}", [
                'fields' => 'sheets.properties(sheetId,title)',
            ]);

        if (! $response->successful()) {
            throw new RuntimeException('Google Sheets API metadata read failed: '.$this->googleErrorMessage($response->status(), $response->json()));
        }

        foreach ($response->json('sheets', []) as $sheet) {
            $properties = $sheet['properties'] ?? [];

            if ((string) ($properties['sheetId'] ?? '') === $gid) {
                $title = str_replace("'", "''", (string) $properties['title']);

                return "'{$title}'!A:ZZ";
            }
        }

        throw new RuntimeException("Could not find a Google Sheet tab with gid {$gid}.");
    }

    private function hasServiceAccountConfig(): bool
    {
        return trim((string) config('services.google_sheets.service_account_json')) !== ''
            && trim((string) config('services.google_sheets.seekers_sheet_id')) !== '';
    }

    private function hasCsvUrlConfig(): bool
    {
        return trim((string) config('services.google_sheets.seekers_csv_url')) !== '';
    }

    private function sheetUrl(): ?string
    {
        $csvUrl = config('services.google_sheets.seekers_csv_url');

        if (is_string($csvUrl) && trim($csvUrl) !== '') {
            return trim($csvUrl);
        }

        $sheetId = config('services.google_sheets.seekers_sheet_id');

        if (! is_string($sheetId) || trim($sheetId) === '') {
            return null;
        }

        $gid = config('services.google_sheets.seekers_gid', '0');

        return sprintf(
            'https://docs.google.com/spreadsheets/d/%s/export?format=csv&gid=%s',
            rawurlencode(trim($sheetId)),
            rawurlencode((string) $gid)
        );
    }

    /**
     * @return array<int, array<string, string|null>>
     */
    private function parseCsv(string $csv): array
    {
        $handle = fopen('php://temp', 'r+');

        if ($handle === false) {
            throw new RuntimeException('Unable to parse Google Sheet CSV.');
        }

        fwrite($handle, $csv);
        rewind($handle);

        $headers = null;
        $rows = [];
        $sourceRowNumber = 0;

        while (($columns = fgetcsv($handle)) !== false) {
            $sourceRowNumber++;

            if ($columns === [null] || $columns === false) {
                continue;
            }

            if ($headers === null) {
                $headers = array_map(fn (?string $header): string => $this->normalizeHeader((string) $header), $columns);

                continue;
            }

            $row = [];

            $rows[] = $this->rowFromColumns($headers, $columns, $sourceRowNumber);
        }

        fclose($handle);

        return $rows;
    }

    /**
     * @param  array<int, array<int, string|null>>  $values
     * @return array<int, array<string, string|null>>
     */
    private function rowsFromValues(array $values): array
    {
        $headers = null;
        $rows = [];
        $sourceRowNumber = 0;

        foreach ($values as $columns) {
            $sourceRowNumber++;

            if (! is_array($columns) || $columns === []) {
                continue;
            }

            if ($headers === null) {
                $headers = array_map(fn (?string $header): string => $this->normalizeHeader((string) $header), $columns);

                continue;
            }

            $row = [];

            $rows[] = $this->rowFromColumns($headers, $columns, $sourceRowNumber);
        }

        return $rows;
    }

    /**
     * @param  array<int, string>  $headers
     * @param  array<int, string|null>  $columns
     * @return array<string, string|null>
     */
    private function rowFromColumns(array $headers, array $columns, int $sourceRowNumber): array
    {
        $row = [];

        foreach ($headers as $index => $header) {
            if ($header === '') {
                continue;
            }

            $value = $columns[$index] ?? null;
            $existingValue = trim((string) ($row[$header] ?? ''));

            if (! array_key_exists($header, $row) || ($existingValue === '' && trim((string) $value) !== '')) {
                $row[$header] = $value;
            }
        }

        if ($this->valueFromRow($row, ['first_name', 'first_nam', 'firstname']) === '') {
            $lastNameIndex = $this->firstHeaderIndex($headers, ['last_name', 'last_nam', 'lastname']);

            if ($lastNameIndex !== null && $lastNameIndex > 0) {
                $firstName = trim((string) ($columns[$lastNameIndex - 1] ?? ''));

                if ($firstName !== '') {
                    $row['first_name'] = $firstName;
                }
            }
        }

        $row['__source_row_number'] = (string) $sourceRowNumber;

        return $row;
    }

    /**
     * @param  array<int, string>  $headers
     * @param  array<int, string>  $needles
     */
    private function firstHeaderIndex(array $headers, array $needles): ?int
    {
        foreach ($needles as $needle) {
            $index = array_search($needle, $headers, true);

            if (is_int($index)) {
                return $index;
            }
        }

        return null;
    }

    private function normalizeHeader(string $header): string
    {
        return Str::of($header)
            ->trim()
            ->lower()
            ->replaceMatches('/[^a-z0-9]+/', '_')
            ->trim('_')
            ->toString();
    }

    private function googleErrorMessage(int $status, mixed $payload): string
    {
        $message = null;

        if (is_array($payload)) {
            $message = $payload['error']['message'] ?? $payload['error_description'] ?? null;
        }

        return trim("HTTP {$status} ".(is_string($message) ? $message : ''));
    }
}
