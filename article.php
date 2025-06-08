<?php
$DEBUG = true;

$article_id = isset($_GET['id']) ? intval($_GET['id']) : 0;
if ($article_id === 0) {
    echo "Article ID manquant.";
    exit;
}

$base_url = "https://admin.ki-leierbud.lu";
$wp_api_url = "$base_url/wp-json/wp/v2/posts/$article_id";
$response = @file_get_contents($wp_api_url);
if (!$response) {
    echo "Impossible de récupérer l'article.";
    exit;
}

$post = json_decode($response, true);
$title = $post['title']['rendered'];
$content = $post['content']['rendered'];
$tags = $post['tags'];

$tag_api_url = "$base_url/wp-json/wp/v2/tags/";
$has_b_hide = false;
$has_f_hide = false;
$b_article_id = null;
$f_article_id = null;
$color_tag_name = null;
$debug_info = [];

foreach ($tags as $tag_id) {
    $tag_info = @json_decode(@file_get_contents($tag_api_url . $tag_id), true);
    if (!$tag_info || !isset($tag_info['name'])) continue;
    $name = trim($tag_info['name']);
    $debug_info[] = "Tag trouvé : $name";

    if ($name === "B-hide") {
        $has_b_hide = true;
        $debug_info[] = "Tag B-hide détecté";
    }

    if ($name === "F-hide") {
        $has_f_hide = true;
        $debug_info[] = "Tag F-hide détecté";
    }

    if (preg_match('/^B-(\d+)$/i', $name, $m)) {
        $b_article_id = intval($m[1]);
        $debug_info[] = "Tag B- détecté : article ID = $b_article_id";
    }

    if (preg_match('/^F-(\d+)$/i', $name, $m)) {
        $f_article_id = intval($m[1]);
        $debug_info[] = "Tag F- détecté : article ID = $f_article_id";
    }

    if (preg_match('/^Color-(.+)$/i', $name, $m)) {
        $color_tag_name = $m[1];
        $debug_info[] = "Tag Color- détecté : $color_tag_name";
    }
}

// Couleurs par défaut
$colors = [
    "header" => "#222",
    "main"   => "#fff",
    "footer" => "#333"
];

// Charger la palette de couleurs depuis fichier JSON
if ($color_tag_name) {
    $json_url = "$base_url/my-json/Color-" . urlencode($color_tag_name) . ".json";
    $json = @file_get_contents($json_url);
    if ($json) {
        $data = json_decode($json, true);
        if (is_array($data)) {
            $colors = array_merge($colors, array_intersect_key($data, $colors));
            $debug_info[] = "Palette chargée depuis $json_url";
        } else {
            $debug_info[] = "Erreur de décodage JSON depuis $json_url";
        }
    } else {
        $debug_info[] = "Fichier JSON introuvable : $json_url";
    }
}

// Fonction pour vérifier si un article existe
function get_post_url_by_article_id($post_id, &$debug_info = null) {
    $url = "https://admin.ki-leierbud.lu/wp-json/wp/v2/posts/$post_id";
    $response = @file_get_contents($url);
    $post = json_decode($response, true);

    if (!empty($post) && isset($post['id'])) {
        if ($debug_info !== null) {
            $debug_info[] = "Article ID $post_id trouvé : " . $post['title']['rendered'];
        }
        return "article.php?id=" . $post_id;
    } else {
        if ($debug_info !== null) {
            $debug_info[] = "Article ID $post_id introuvable.";
        }
        return "";
    }
}

// Générer les liens vers les articles précédents / suivants
$prev_url = (!$has_b_hide && $b_article_id) ? get_post_url_by_article_id($b_article_id, $debug_info) : "";
$next_url = (!$has_f_hide && $f_article_id) ? get_post_url_by_article_id($f_article_id, $debug_info) : "";
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <title><?= htmlspecialchars($title) ?></title>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <style>
        body, html {
            margin: 0;
            padding: 0;
            font-family: sans-serif;
        }
        header, footer {
            position: fixed;
            width: 100%;
            left: 0;
            padding: 1em;
            color: white;
            z-index: 1000;
        }
        header {
            top: 0;
            background-color: <?= $colors['header'] ?>;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        main {
            margin-top: 70px;
            margin-bottom: 70px;
            background-color: <?= $colors['main'] ?>;
            padding: 1em;
        }
        footer {
            bottom: 0;
            background-color: <?= $colors['footer'] ?>;
            display: flex;
            justify-content: space-around;
            align-items: center;
        }
        .icon-btn {
            background: none;
            border: none;
            color: white;
            font-size: 24px;
            text-decoration: none;
        }
        select {
            font-size: 1em;
        }
        .debug {
            background: #eee;
            color: #444;
            font-size: 0.85em;
            padding: 0.5em;
            border-bottom: 1px solid #ccc;
        }
    </style>
    <script>
        function changeLang(select) {
            const lang = select.value;
            const url = new URL(window.location.href);
            url.searchParams.set('lang', lang);
            window.location.href = url.toString();
        }
    </script>
</head>
<body>

<?php if ($DEBUG): ?>
    <pre class="debug"><?= implode("\n", array_map('htmlspecialchars', $debug_info)) ?></pre>
<?php endif; ?>

<header>
    <div><?= htmlspecialchars($title) ?></div>
    <select onchange="changeLang(this)">
        <option value="en">EN</option>
        <option value="fr">FR</option>
        <option value="de">DE</option>
        <option value="pt">PT</option>
        <option value="lb">LB</option>
    </select>
</header>

<main>
    <?= $content ?>
</main>

<footer>
    <?php if ($prev_url): ?>
        <a href="<?= htmlspecialchars($prev_url) ?>" class="icon-btn">&#8592;</a>
    <?php endif; ?>
    <a href="index.html" class="icon-btn">&#8962;</a>
    <a href="index-toc.html" class="icon-btn">&#9776;</a>
    <?php if ($next_url): ?>
        <a href="<?= htmlspecialchars($next_url) ?>" class="icon-btn">&#8594;</a>
    <?php endif; ?>
</footer>

</body>
</html>
