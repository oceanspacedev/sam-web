<?php
$domain = 'https://grosir.mediaselularindonesia.com/';
$sitemap_name = 'sitemap';
$keywords = file('tupai.txt', FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
$max_links_per_sitemap = 10000;
$today = date('Y-m-d');

$sitemap_index = '<?xml version="1.0" encoding="UTF-8"?>' . "\n";
$sitemap_index .= '<sitemapindex xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">' . "\n";

$sitemap_files = array();

foreach ($keywords as $i => $keyword) {
    $sitemap_file_number = ceil(($i + 1) / $max_links_per_sitemap);

    if (!isset($sitemap_files[$sitemap_file_number])) {
        $sitemap_files[$sitemap_file_number] = '<?xml version="1.0" encoding="UTF-8"?>' . "\n";
        $sitemap_files[$sitemap_file_number] .= '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">' . "\n";
    }

    // Pertahankan huruf kapital, hanya ubah spasi menjadi dash
    $slug = str_replace(' ', '-', trim($keyword));
    $url = $domain . $slug;

    $sitemap_files[$sitemap_file_number] .= "  <url>\n";
    $sitemap_files[$sitemap_file_number] .= "    <loc>" . htmlspecialchars($url) . "</loc>\n";
    $sitemap_files[$sitemap_file_number] .= "    <lastmod>$today</lastmod>\n";
    $sitemap_files[$sitemap_file_number] .= "    <changefreq>daily</changefreq>\n";
    $sitemap_files[$sitemap_file_number] .= "    <priority>1.0</priority>\n";
    $sitemap_files[$sitemap_file_number] .= "  </url>\n";
}

// Simpan semua sitemap bagian
foreach ($sitemap_files as $sitemap_file_number => &$sitemap_file) {
    $sitemap_file .= "</urlset>\n";
    file_put_contents("$sitemap_name-$sitemap_file_number.xml", $sitemap_file);

    $sitemap_index .= "  <sitemap>\n";
    $sitemap_index .= "    <loc>" . $domain . $sitemap_name . "-$sitemap_file_number.xml</loc>\n";
    $sitemap_index .= "    <lastmod>$today</lastmod>\n";
    $sitemap_index .= "  </sitemap>\n";
}

$sitemap_index .= "</sitemapindex>\n";
file_put_contents($sitemap_name . '.xml', $sitemap_index);

echo "Sitemap.xml dan semua bagian sudah selesai king Tuwaga!";
?>