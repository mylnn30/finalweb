<?php

namespace App\Service;

use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

class DocumentStorageService
{
    public function __construct(#[Autowire('%kernel.project_dir%')] private readonly string $projectDir)
    {
    }

    public function store(UploadedFile $file): string
    {
        $directory = $this->projectDir . '/var/private/loan_documents';
        if (!is_dir($directory) && !mkdir($directory, 0700, true) && !is_dir($directory)) {
            throw new \RuntimeException('Could not create private document storage.');
        }

        $filename = bin2hex(random_bytes(24)) . '.' . ($file->guessExtension() ?: 'bin');
        $file->move($directory, $filename);

        return $filename;
    }

    public function absolutePath(string $filename): string
    {
        if ($filename === '' || basename($filename) !== $filename) {
            throw new \InvalidArgumentException('Invalid document name.');
        }

        return $this->projectDir . '/var/private/loan_documents/' . $filename;
    }
}