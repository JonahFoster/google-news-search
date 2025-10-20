<?php
if (! defined('ABSPATH')) exit;

/*
 * Plugin Name: Google News Search
 * Description: A plugin that provides a search form to search Google News.
 * Version: 1.0.0
 * Requires at least: 5.2
 * Requires PHP: 7.2
 * Author: Jonah Foster
 * Author URI: https://www.jonahfoster.com/
 * License: GPL v2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Update URI: https://www.jonahfoster.com/
 * Text Domain: google-news-search
 */

function gns_enqueue_styles()
{
    global $post;
    if (is_a($post, 'WP_Post') && has_shortcode($post->post_content, 'google_news_search')) {
        wp_enqueue_style(
            'gns-styles',
            plugin_dir_url(__FILE__) . 'assets/css/style.css',
            array(),
            '1.0.0',
            'all'
        );
    }
}
add_action('wp_enqueue_scripts', 'gns_enqueue_styles');

function gns_search_form()
{
    ob_start();
    $search_term = '';
    $results = [];
    $error = '';

    if (isset($_POST['gns_search']) && !empty($_POST['gns_query'])) {
        // CSRF protection
        if (!isset($_POST['gns_nonce']) || !wp_verify_nonce($_POST['gns_nonce'], 'gns_search_action')) {
            $error = 'Security check failed. Please refresh and try again.';
        } else {
            $search_term = sanitize_text_field($_POST['gns_query']);
            $cache_key = 'gns_results_' . md5($search_term);
            $cached_results = get_transient($cache_key);

            if ($cached_results !== false) {
                $results = $cached_results;
            } else {
                $url = 'https://news.google.com/rss/search?q=' . urlencode($search_term);
                $response = wp_remote_get($url);

                if (!is_wp_error($response)) {
                    $body = wp_remote_retrieve_body($response);
                    $xml = simplexml_load_string($body);

                    if ($xml && isset($xml->channel->item)) {
                        foreach ($xml->channel->item as $item) {
                            $results[] = array(
                                'title' => (string) $item->title,
                                'link' => (string) $item->link,
                                'pubDate' => (string) $item->pubDate,
                            );
                        }
                        set_transient($cache_key, $results, 5 * MINUTE_IN_SECONDS);
                    }
                } else {
                    $error = 'Could not fetch news. Please try again.';
                }
            }
        }
    }
?>

    <div class="gns-container">
        <form method="post" class="gns-form" role="search" aria-label="Google News Search">
            <?php wp_nonce_field('gns_search_action', 'gns_nonce'); ?>
            <label for="gns-query-input" class="gns-label">
                Search Google News
            </label>
            <input
                type="text"
                id="gns-query-input"
                name="gns_query"
                class="gns-input"
                placeholder="Enter your search term..."
                value="<?php echo esc_attr($search_term); ?>"
                aria-describedby="<?php echo $error ? 'gns-error-message' : ''; ?>"
                aria-invalid="<?php echo $error ? 'true' : 'false'; ?>"
                required />
            <button type="submit" name="gns_search" class="gns-button" aria-label="Search news articles">
                Search
            </button>
        </form>

        <?php if ($error): ?>
            <div
                id="gns-error-message"
                class="gns-error"
                role="alert">
                <?php echo esc_html($error); ?>
            </div>
        <?php endif; ?>

        <?php if (!empty($results)): ?>
            <div class="gns-results" role="region" aria-label="Search results">
                <h2 class="gns-results-heading">Found <?php echo count($results); ?> articles</h2>
                <?php foreach ($results as $index => $article): ?>
                    <article class="gns-result">
                        <h3 class="gns-result-title">
                            <a
                                href="<?php echo esc_url($article['link']); ?>"
                                target="_blank"
                                rel="noopener noreferrer">
                                <?php echo esc_html($article['title']); ?>
                            </a>
                        </h3>
                        <?php if (!empty($article['pubDate'])): ?>
                            <time class="gns-date" datetime="<?php echo esc_attr(date('c', strtotime($article['pubDate']))); ?>">
                                <?php echo date('F j, Y', strtotime($article['pubDate'])); ?>
                            </time>
                        <?php endif; ?>
                    </article>
                <?php endforeach; ?>
            </div>
        <?php elseif (!empty($search_term) && empty($error)): ?>
            <p role="status">No results found for "<?php echo esc_html($search_term); ?>"</p>
        <?php endif; ?>
    </div>

<?php
    return ob_get_clean();
}

add_shortcode('google_news_search', 'gns_search_form');
