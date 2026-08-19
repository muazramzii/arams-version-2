<?php
// ============================================================
//  Reusable lecturer avatar: shows the uploaded profile photo
//  when present, otherwise a teal initials circle (fallback).
// ============================================================
if (!function_exists('lecAvatar')) {
    function lecAvatar(?string $photo, string $name, int $size = 38): string {
        $dir = __DIR__ . '/../assets/images/profiles/';
        $url = ($photo && file_exists($dir . $photo))
             ? '/arams/assets/images/profiles/' . htmlspecialchars($photo)
             : '';
        if ($url !== '') {
            return '<img src="' . $url . '" alt="" loading="lazy" '
                 . 'style="width:' . $size . 'px;height:' . $size . 'px;border-radius:50%;'
                 . 'object-fit:cover;flex-shrink:0;border:1px solid var(--border)">';
        }
        $name = trim($name);
        $ini  = $name !== '' ? strtoupper(mb_substr($name, 0, 2, 'UTF-8')) : '?';
        return '<span style="width:' . $size . 'px;height:' . $size . 'px;border-radius:50%;'
             . 'background:var(--teal);color:#fff;display:inline-flex;align-items:center;'
             . 'justify-content:center;font-size:' . round($size * 0.36) . 'px;font-weight:700;'
             . 'flex-shrink:0">' . htmlspecialchars($ini) . '</span>';
    }
}