<?php
function product_public_url(string $stored): string {
    if (str_starts_with($stored, 'uploads/')) return $stored;
    if (str_starts_with($stored, 'products/')) return 'uploads/' . $stored;
    return $stored ?: 'images/demo/obra-01.svg';
}
function artwork_public_url(string $stored): string {
    if (str_starts_with($stored, 'uploads/')) return $stored;
    if (str_starts_with($stored, 'artworks/')) return 'uploads/' . $stored;
    return $stored ?: 'images/demo/obra-01.svg';
}

function artwork_storage_path(string $stored): ?string {
    if (str_starts_with($stored, 'uploads/artworks/')) {
        return dirname(__DIR__) . '/uploads/artworks/' . basename($stored);
    }
    if (str_starts_with($stored, 'artworks/')) {
        return dirname(__DIR__) . '/uploads/artworks/' . basename($stored);
    }
    return null;
}
