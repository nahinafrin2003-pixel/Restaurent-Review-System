<?php

namespace App\Support;

/** Upload validation per evidence type (extension + MIME + size in KB). */
class EvidenceRules
{
    public const TYPES = [
        'image' => ['field' => 'photos', 'mimes' => 'jpg,jpeg,png,webp', 'max' => 5120, 'hint' => 'JPG/PNG/WEBP, up to 5 MB each'],
        'video' => ['field' => 'videos', 'mimes' => 'mp4,webm,mov', 'max' => 20480, 'hint' => 'MP4/WEBM/MOV, up to 20 MB each'],
        'audio' => ['field' => 'audio', 'mimes' => 'mp3,wav,m4a,ogg,webm', 'max' => 10240, 'hint' => 'MP3/WAV/M4A/OGG, up to 10 MB each'],
        'receipt' => ['field' => 'receipts', 'mimes' => 'pdf,jpg,jpeg,png', 'max' => 5120, 'hint' => 'PDF or photo of the receipt, up to 5 MB each'],
    ];

    public const MAX_FILES_PER_TYPE = 5;

    public static function rules(): array
    {
        $rules = [];
        foreach (self::TYPES as $config) {
            $rules[$config['field']] = 'nullable|array|max:' . self::MAX_FILES_PER_TYPE;
            $rules[$config['field'] . '.*'] = 'file|mimes:' . $config['mimes'] . '|max:' . $config['max'];
        }

        return $rules;
    }

    public static function messages(): array
    {
        $messages = [];
        foreach (self::TYPES as $type => $config) {
            $messages[$config['field'] . '.*.mimes'] = ucfirst($type) . ' files must be: ' . $config['hint'] . '.';
            $messages[$config['field'] . '.*.max'] = ucfirst($type) . ' files must be ' . $config['hint'] . '.';
            $messages[$config['field'] . '.*.uploaded'] = 'A ' . $type . ' file failed to upload (it may exceed the server upload limit).';
        }

        return $messages;
    }

    /** Collects uploaded files from the request grouped by evidence type. */
    public static function collect(\Illuminate\Http\Request $request): array
    {
        $files = [];
        foreach (self::TYPES as $type => $config) {
            $files[$type] = array_filter((array) $request->file($config['field'], []));
        }

        return $files;
    }
}
