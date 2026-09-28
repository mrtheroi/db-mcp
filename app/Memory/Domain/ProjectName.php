<?php

namespace App\Memory\Domain;

final class ProjectName
{
    /**
     * Canonical form of a project name: trimmed, lowercased, with repeated
     * dashes and underscores collapsed. An empty name means no project.
     */
    public static function normalize(?string $name): ?string
    {
        $name = mb_strtolower(trim($name ?? ''));

        if ($name === '') {
            return null;
        }

        return preg_replace(['/-{2,}/', '/_{2,}/'], ['-', '_'], $name);
    }
}
