<?php
class PageBuilderRenderer {

    public static function renderPage($jsonPayload) {
        $blocks = json_decode($jsonPayload, true);
        if (!$blocks || !is_array($blocks)) return '';

        $htmlOutput = '';
        foreach ($blocks as $block) {
            $htmlOutput .= self::compileBlock($block);
        }
        return $htmlOutput;
    }

    private static function compileBlock($block) {
        $type = $block['type'] ?? '';
        $content = $block['content'] ?? [];

        switch ($type) {
            case 'hero':
                $title = htmlspecialchars($content['title'] ?? '');
                $subtitle = htmlspecialchars($content['subtitle'] ?? '');
                $btnText = htmlspecialchars($content['btn_text'] ?? '');
                $btnLink = htmlspecialchars($content['btn_link'] ?? '#');
                return "
                <section class='bg-gray-900 text-white py-20 px-6 text-center'>
                    <h1 class='text-5xl font-bold mb-4'>{$title}</h1>
                    <p class='text-xl text-gray-300 mb-8'>{$subtitle}</p>
                    <a href='{$btnLink}' class='bg-blue-600 hover:bg-blue-700 px-6 py-3 rounded-md text-white font-semibold'>{$btnText}</a>
                </section>";

            case 'feature_grid':
                $itemsHtml = '';
                foreach ($content['items'] ?? [] as $item) {
                    $itemTitle = htmlspecialchars($item['title'] ?? '');
                    $itemDesc = htmlspecialchars($item['desc'] ?? '');
                    $itemsHtml .= "
                    <div class='p-6 bg-white rounded-lg shadow-md border'>
                        <h3 class='text-xl font-bold mb-2'>{$itemTitle}</h3>
                        <p class='text-gray-600'>{$itemDesc}</p>
                    </div>";
                }
                return "
                <section class='py-16 px-6 max-w-7xl mx-auto'>
                    <div class='grid grid-cols-1 md:grid-cols-3 gap-8'>{$itemsHtml}</div>
                </section>";

            default:
                return '';
        }
    }
}