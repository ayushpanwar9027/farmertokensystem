<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Request;
use App\Core\Response;
use App\Exceptions\NotFoundException;
use App\Services\AuditService;
use App\Services\LocalizationService;
use App\Services\RbacService;
use App\Services\TranslationService;

class TranslationsController
{
    private TranslationService $translations;
    private LocalizationService $localization;
    private RbacService $rbac;
    private AuditService $audit;

    public function __construct()
    {
        $this->translations = new TranslationService();
        $this->localization = new LocalizationService();
        $this->rbac = new RbacService();
        $this->audit = new AuditService();
    }

    public function show(Request $request): void
    {
        $locale = $this->localization->normalize((string) $request->getParam('locale', 'en'));
        if (!$this->localization->isSupported($locale)) {
            throw new NotFoundException('INVALID_LOCALE', 'Locale not supported', 404, [
                'supported' => $this->localization->activeLocales(),
            ]);
        }

        Response::success([
            'locale' => $locale,
            'languages' => $this->translations->languages(),
            'translations' => $this->translations->packFor($locale),
        ]);
    }

    public function languages(Request $request): void
    {
        Response::success([
            'languages' => $this->translations->languages(),
            'default_locale' => $this->localization->defaultLocale(),
        ]);
    }

    public function index(Request $request): void
    {
        $actor = $this->requireActor($request);
        $this->rbac->assertCan($actor, 'translations.view', 'You do not have permission to view translations');

        $locale = $this->localization->normalize((string) $request->query('locale', 'en'));
        if (!$this->localization->isSupported($locale)) {
            throw new NotFoundException('INVALID_LOCALE', 'Locale not supported', 404);
        }

        Response::success([
            'locale' => $locale,
            'translations' => $this->translations->listForAdmin(
                $locale,
                (string) $request->query('q', ''),
                (int) $request->query('page', 1),
                (int) $request->query('per_page', 50)
            ),
        ]);
    }

    public function export(Request $request): void
    {
        $actor = $this->requireActor($request);
        $this->rbac->assertCan($actor, 'translations.view', 'You do not have permission to view translations');

        $locale = $this->localization->normalize((string) $request->query('locale', 'en'));
        if (!$this->localization->isSupported($locale)) {
            throw new NotFoundException('INVALID_LOCALE', 'Locale not supported', 404);
        }

        Response::success($this->translations->export($locale));
    }

    public function import(Request $request): void
    {
        $actor = $this->requireActor($request);
        $this->rbac->assertCan($actor, 'translations.manage', 'You do not have permission to manage translations');

        $locale = $this->localization->normalize((string) $request->input('locale', 'en'));
        if (!$this->localization->isSupported($locale)) {
            throw new NotFoundException('INVALID_LOCALE', 'Locale not supported', 404);
        }

        $translations = $request->input('translations');
        if (!is_array($translations) && isset($_FILES['file']) && is_array($_FILES['file'])) {
            $translations = $this->parseImportFile($_FILES['file']);
        }

        if (!is_array($translations) || empty($translations)) {
            throw new \App\Exceptions\ValidationException([
                'translations' => ['Provide translations as a JSON object or an import file'],
            ]);
        }

        $result = $this->translations->import($translations, $locale, (int) $actor['id']);

        $this->audit->log([
            'user_id' => (int) $actor['id'],
            'user_name' => $actor['name'] ?? '',
            'user_role' => $actor['role'] ?? '',
            'action' => 'TRANSLATIONS_IMPORTED',
            'module' => 'LANGUAGE',
            'entity_type' => 'translations',
            'entity_id' => null,
            'new_value' => $result,
            'reason' => 'admin_translation_import',
        ]);

        Response::success($result);
    }

    public function bulkUpdate(Request $request): void
    {
        $actor = $this->requireActor($request);
        $this->rbac->assertCan($actor, 'translations.manage', 'You do not have permission to manage translations');

        $locale = $this->localization->normalize((string) $request->getParam('code', ''));
        if (!$this->localization->isSupported($locale)) {
            throw new NotFoundException('INVALID_LOCALE', 'Locale not supported', 404);
        }

        $translations = $request->input('translations');
        if (!is_array($translations) || empty($translations)) {
            throw new \App\Exceptions\ValidationException([
                'translations' => ['At least one translation must be provided'],
            ]);
        }

        $result = $this->translations->bulkUpsert($locale, $translations, (int) $actor['id']);

        $this->audit->log([
            'user_id' => (int) $actor['id'],
            'user_name' => $actor['name'] ?? '',
            'user_role' => $actor['role'] ?? '',
            'action' => 'TRANSLATIONS_UPDATED',
            'module' => 'LANGUAGE',
            'entity_type' => 'translations',
            'entity_id' => null,
            'new_value' => $result,
            'reason' => 'admin_translation_upsert',
        ]);

        Response::success([
            'message' => 'Translations updated',
            'locale' => $locale,
            'updated' => $result['updated'],
            'skipped' => $result['skipped'],
            'invalid' => $result['invalid'],
        ]);
    }

    public function deleteKey(Request $request): void
    {
        $actor = $this->requireActor($request);
        $this->rbac->assertCan($actor, 'translations.manage', 'You do not have permission to manage translations');

        $locale = $this->localization->normalize((string) $request->getParam('code', ''));
        if (!$this->localization->isSupported($locale)) {
            throw new NotFoundException('INVALID_LOCALE', 'Locale not supported', 404);
        }

        $key = trim((string) $request->input('key', ''));
        if ($key === '') {
            throw new \App\Exceptions\ValidationException(['key' => ['Translation key is required']]);
        }

        $languageId = (new \App\Models\Language())->idByCode($locale);
        if ($languageId === null) {
            throw new NotFoundException('INVALID_LOCALE', 'Locale not supported', 404);
        }

        $translation = (new \App\Models\Translation())->findBy('language_id', $languageId);
        \App\Core\Database::delete(
            'translations',
            'language_id = ? AND translation_key = ?',
            [$languageId, $key]
        );

        $this->translations->invalidate();

        $this->audit->log([
            'user_id' => (int) $actor['id'],
            'user_name' => $actor['name'] ?? '',
            'user_role' => $actor['role'] ?? '',
            'action' => 'TRANSLATION_DELETED',
            'module' => 'LANGUAGE',
            'entity_type' => 'translations',
            'entity_id' => null,
            'new_value' => ['locale' => $locale, 'key' => $key],
            'reason' => 'admin_translation_delete',
        ]);

        Response::success([
            'deleted' => true,
            'locale' => $locale,
            'key' => $key,
        ]);
    }

    private function parseImportFile(array $file): array
    {
        $tmp = $file['tmp_name'] ?? null;
        if (!is_string($tmp) || !is_file($tmp)) {
            throw new \App\Exceptions\ValidationException(['file' => ['Uploaded import file is invalid']]);
        }

        if (($file['error'] ?? UPLOAD_ERR_OK) !== UPLOAD_ERR_OK) {
            throw new \App\Exceptions\ValidationException(['file' => ['Upload failed with an upload error']]);
        }

        $maxBytes = (int) (getenv('FILE_UPLOAD_MAX_SIZE') ?: 2097152);
        if ((int) ($file['size'] ?? 0) > $maxBytes) {
            throw new \App\Exceptions\ValidationException(['file' => ['Import file exceeds the maximum allowed size of ' . (int) ($maxBytes / 1024) . ' KB']]);
        }

        $original = strtolower((string) ($file['name'] ?? ''));

        if (str_ends_with($original, '.json')) {
            $raw = (string) file_get_contents($tmp);
            $decoded = json_decode($raw, true);
            if (!is_array($decoded)) {
                throw new \App\Exceptions\ValidationException(['file' => ['Invalid JSON in import file']]);
            }
            return $decoded;
        }

        if (str_ends_with($original, '.csv')) {
            $translations = [];
            $handle = fopen($tmp, 'r');
            if ($handle === false) {
                throw new \App\Exceptions\ValidationException(['file' => ['Unable to read import file']]);
            }
            while (($row = fgetcsv($handle)) !== false) {
                if (count($row) >= 2 && trim((string) $row[0]) !== '') {
                    $translations[trim((string) $row[0])] = (string) $row[1];
                }
            }
            fclose($handle);
            return $translations;
        }

        throw new \App\Exceptions\ValidationException([
            'file' => ['Import file must be CSV or JSON'],
        ]);
    }

    private function requireActor(Request $request): array
    {
        $user = $request->getUser();
        if ($user === null) {
            throw new \App\Exceptions\AuthorizationException('Authentication required');
        }
        return $user;
    }
}