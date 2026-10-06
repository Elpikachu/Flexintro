<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;

class SeedFilesCommand extends Command
{
    protected $signature = 'demo:seed-files {--force : Rewrite files that already exist}';

    protected $description = 'Write the demo course files to the object storage bucket (idempotent)';

    public function handle(): int
    {
        $disk = Storage::disk(config('demo.disk'));
        $prefix = trim(config('demo.files_prefix'), '/');

        $written = 0;
        $skipped = 0;

        foreach ($this->files() as $name => $contents) {
            $path = $prefix.'/'.$name;

            if (! $this->option('force') && $disk->exists($path)) {
                $skipped++;
                $this->line("  skipped  {$path}");

                continue;
            }

            $disk->put($path, $contents);
            $written++;
            $this->line("  wrote    {$path} (".number_format(strlen($contents)).' bytes)');
        }

        $this->newLine();
        $this->info("Done. {$written} written, {$skipped} already there.");

        return self::SUCCESS;
    }

    /**
     * Twelve small files: eight course thumbnails and four lesson documents.
     * Everything stays well under 5 MB so any of them opens instantly on a
     * phone hotspot.
     *
     * @return array<string, string>
     */
    private function files(): array
    {
        $courses = [
            ['code' => 'C001', 'title' => 'Onboarding essentials', 'hue' => 252],
            ['code' => 'C002', 'title' => 'Safety at work', 'hue' => 68],
            ['code' => 'C003', 'title' => 'Customer conversations', 'hue' => 198],
            ['code' => 'C004', 'title' => 'Data protection basics', 'hue' => 320],
            ['code' => 'C005', 'title' => 'Team leadership', 'hue' => 24],
            ['code' => 'C006', 'title' => 'Quality standards', 'hue' => 150],
            ['code' => 'C007', 'title' => 'Field operations', 'hue' => 280],
            ['code' => 'C008', 'title' => 'Annual compliance', 'hue' => 8],
        ];

        $files = [];

        foreach ($courses as $course) {
            $files["thumbnails/{$course['code']}-thumbnail.svg"] = $this->thumbnail($course);
        }

        foreach (array_slice($courses, 0, 3) as $index => $course) {
            $files["lessons/{$course['code']}-lesson-01.json"] = $this->lesson($course, $index + 1);
        }

        $files['lessons/README.txt'] = $this->readme($courses);

        return $files;
    }

    private function thumbnail(array $course): string
    {
        $hue = $course['hue'];
        $title = htmlspecialchars($course['title'], ENT_XML1);
        $code = $course['code'];

        return <<<SVG
        <svg xmlns="http://www.w3.org/2000/svg" width="640" height="360" viewBox="0 0 640 360" role="img" aria-label="{$title}">
          <defs>
            <linearGradient id="g" x1="0" y1="0" x2="1" y2="1">
              <stop offset="0" stop-color="hsl({$hue} 90% 62%)"/>
              <stop offset="1" stop-color="hsl({$hue} 70% 34%)"/>
            </linearGradient>
          </defs>
          <rect width="640" height="360" fill="url(#g)"/>
          <circle cx="536" cy="64" r="120" fill="#ffffff" opacity="0.10"/>
          <circle cx="92" cy="316" r="90" fill="#0C0F10" opacity="0.12"/>
          <text x="48" y="188" font-family="Arial, Helvetica, sans-serif" font-size="46" font-weight="700" fill="#ffffff">{$title}</text>
          <text x="48" y="238" font-family="Arial, Helvetica, sans-serif" font-size="26" fill="#ffffff" opacity="0.85">Course {$code}</text>
        </svg>
        SVG;
    }

    private function lesson(array $course, int $number): string
    {
        return json_encode([
            'course_code' => $course['code'],
            'title' => $course['title'],
            'lesson' => $number,
            'duration_minutes' => 12 + ($number * 3),
            'sections' => [
                ['heading' => 'Before you start', 'minutes' => 2],
                ['heading' => 'Walkthrough', 'minutes' => 7],
                ['heading' => 'Check your understanding', 'minutes' => 3],
            ],
            'assets' => ["thumbnails/{$course['code']}-thumbnail.svg"],
        ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES)."\n";
    }

    private function readme(array $courses): string
    {
        $lines = [
            'Demo course library',
            '',
            'These files sit in the application object storage bucket, behind an',
            'internal only endpoint. They reach the browser through a Laravel route,',
            'so the application access rules apply to every download.',
            '',
            'Courses in this library:',
        ];

        foreach ($courses as $course) {
            $lines[] = "  {$course['code']}  {$course['title']}";
        }

        return implode("\n", $lines)."\n";
    }
}
