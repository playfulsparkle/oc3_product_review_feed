<?php
class ControllerExtensionFeedPsProductReviewFeed extends Controller
{
    /**
     * Generates and outputs the Google Product Review Feed XML.
     *
     * This method checks if the sitemap feature is enabled in the configuration.
     * If it is, it initializes the XMLWriter, sets the XML header, and populates
     * the sitemap with URLs for products, categories, manufacturers, and
     * information pages based on the active languages.
     *
     * @return void
     */
    public function index()
    {
        if (!$this->config->get('feed_ps_product_review_feed_status')) {
            return;
        }

        $this->load->model('setting/setting');

        $login = (string) $this->model_setting_setting->getSettingValue('feed_ps_product_review_feed_login', $this->config->get('config_store_id'));
        $password = (string) $this->model_setting_setting->getSettingValue('feed_ps_product_review_feed_password', $this->config->get('config_store_id'));

        if ($login && $password) {
            header('Cache-Control: no-cache, must-revalidate, max-age=0');

            if (!isset($_SERVER['PHP_AUTH_USER']) || !isset($_SERVER['PHP_AUTH_PW'])) {
                header('WWW-Authenticate: Basic realm="ps_product_review_feed"');
                header('HTTP/1.1 401 Unauthorized');
                echo 'Invalid credentials';
                exit;
            } else {
                if ($_SERVER['PHP_AUTH_USER'] !== $login || $_SERVER['PHP_AUTH_PW'] !== $password) {
                    header('WWW-Authenticate: Basic realm="ps_product_review_feed"');
                    header('HTTP/1.1 401 Unauthorized');
                    echo 'Invalid credentials';
                    exit;
                }
            }
        }

        $this->load->model('localisation/language');
        $this->load->model('extension/feed/ps_product_review_feed');

        $languages = $this->model_localisation_language->getLanguages();

        $language_id = (int) $this->config->get('config_language_id');
        $old_language_id = $language_id;

        if (isset($this->request->get['language']) && isset($languages[$this->request->get['language']])) {
            $cur_language = $languages[$this->request->get['language']];

            $language_id = $cur_language['language_id'];
        }

        $this->config->set('config_language_id', $language_id);

        $xml = new XMLWriter();
        $xml->openMemory();
        $xml->startDocument('1.0', 'UTF-8');

        $reviews = $this->model_extension_feed_ps_product_review_feed->getReviews();

        // Start <feed> element
        // @see https://developers.google.com/product-review-feeds/schema
        $xml->startElement('feed'); // Start <feed>
        $xml->writeAttribute('xmlns:vc', 'http://www.w3.org/2007/XMLSchema-versioning');
        $xml->writeAttribute('xmlns:xsi', 'http://www.w3.org/2001/XMLSchema-instance');
        $xml->writeAttribute('xsi:noNamespaceSchemaLocation', 'http://www.google.com/shopping/reviews/schema/product/2.4/product_reviews.xsd');

        $xml->writeElement('version', '2.4');

        $xml->startElement('aggregator'); // Start <aggregator>
        $xml->writeElement('name', $this->config->get('config_name'));
        $xml->endElement(); // End <aggregator>

        $xml->startElement('publisher'); // Start <publisher>

        $xml->writeElement('name', $this->config->get('config_name')); // Store name

        if ($this->request->server['HTTPS']) {
            $server = $this->config->get('config_ssl');
        } else {
            $server = $this->config->get('config_url');
        }

        if (is_file(DIR_IMAGE . $this->config->get('config_icon'))) {
            $xml->startElement('favicon'); // Start <favicon>
            $xml->writeCData($server . 'image/' . $this->config->get('config_icon')); // Store icon URL
            $xml->endElement(); // End <favicon>
        }

        $xml->endElement(); // End <publisher>

        $xml->startElement('reviews'); // Start <reviews>

        foreach ($reviews as $review) {
            $xml->startElement('review'); // Start <review>

            $xml->writeElement('review_id', $review['review_id']);

            $xml->startElement('reviewer'); // Start <reviewer>

            $xml->startElement('name'); // Start <name>
            $xml->writeCData($review['author']); // Reviewer name
            $xml->endElement(); // End <name>

            if ($review['customer_id'] > 0) {
                $xml->writeElement('reviewer_id', $review['customer_id']);
            }

            $xml->endElement(); // End <reviewer>

            // $xml->writeElement('title'); // OpenCart does not support review title

            $xml->startElement('content'); // Start <content>
            $xml->writeCData($review['text']);
            $xml->endElement(); // End <content>

            if (!empty($review['date_added'])) {
                $xml->writeElement('review_timestamp', gmdate('Y-m-d\TH:i:s\Z', strtotime($review['date_added'])));
            }

            // review_language: ISO 639-1, ex: en, sk, hu
            $lang_code = strtolower(substr((string) $this->config->get('config_language'), 0, 2));

            if ($lang_code) {
                $xml->writeElement('review_language', $lang_code);
            }

            // review_country: ISO 3166-1 alpha-2, set from config or hardcode if single-country
            $country = (string) $this->config->get('config_country_code'); // if you have it

            if ($country) {
                $xml->writeElement('review_country', strtoupper($country));
            }

            $product_link = $this->url->link('product/product', 'product_id=' . $review['product_id']);

            $xml->startElement('review_url'); // Start <review_url>
            $xml->writeAttribute('type', 'singleton'); // @see https://developers.google.com/shopping/reviews/schema/product/2.3/product_reviews#review_url
            $xml->writeCData(str_replace('&amp;', '&', $product_link) . '#review-' . (int) $review['review_id']); // Product URL
            $xml->endElement(); // End <review_url>

            $xml->startElement('ratings'); // Start <ratings>

            $xml->startElement('overall'); // Start <overall>
            $xml->writeAttribute('min', '1'); // @see https://developers.google.com/shopping/reviews/schema/product/2.3/product_reviews#overall
            $xml->writeAttribute('max', '5'); // @see https://developers.google.com/shopping/reviews/schema/product/2.3/product_reviews#overall

            $rating = (float) $review['rating'];

            if ($rating < 1) {
                $rating = 1;
            } elseif ($rating > 5) {
                $rating = 5;
            }

            $xml->text(number_format($rating, 1, '.', '')); // Overall rating
            $xml->endElement(); // End <overall>

            $xml->endElement(); // End <ratings>

            $xml->startElement('products'); // Start <products>

            $xml->startElement('product'); // Start <product>

            $xml->startElement('product_ids'); // Start <product_ids>

            // Brand, MPN, and GTIN candidates
            $brand = isset($review['manufacturer_name']) ? trim((string) $review['manufacturer_name']) : '';
            $mpn = isset($review['mpn']) ? trim((string) $review['mpn']) : '';

            $upc = isset($review['upc']) ? trim((string) $review['upc']) : '';
            $ean = isset($review['ean']) ? trim((string) $review['ean']) : '';
            $jan = isset($review['jan']) ? trim((string) $review['jan']) : '';
            $isbn = isset($review['isbn']) ? trim((string) $review['isbn']) : '';

            // Prefer EAN, then UPC, then JAN
            $gtin = ($ean !== '') ? $ean : $upc;

            if ($gtin === '') {
                $gtin = ($jan !== '') ? $jan : '';
            }

            // If still empty, allow ISBN only if it is 13 digits
            if ($gtin === '' && $isbn !== '' && preg_match('/^[0-9]{13}$/', $isbn)) {
                $gtin = $isbn;
            }

            // Block GTIN if it contains any non-digit characters
            if ($gtin !== '' && preg_match('/[^0-9]/', $gtin)) {
                $gtin = '';
            }

            // GTIN must be gtins/gtin (not ean, not upc, not mpn)
            if ($gtin !== '') {
                $xml->startElement('gtins');

                $xml->startElement('gtin');
                $xml->writeCData($gtin);
                $xml->endElement(); // gtin

                $xml->endElement(); // gtins
            }

            // MPN
            if ($mpn !== '') {
                $xml->startElement('mpns');

                $xml->startElement('mpn');
                $xml->writeCData($mpn);
                $xml->endElement(); // mpn

                $xml->endElement(); // mpns
            }

            // SKU
            if (!empty($review['sku'])) {
                $xml->startElement('skus');

                $xml->startElement('sku');
                $xml->writeCData($review['sku']);
                $xml->endElement(); // sku

                $xml->endElement(); // skus
            }

            // Brand
            if ($brand !== '') {
                $xml->startElement('brands');

                $xml->startElement('brand');
                $xml->writeCData(html_entity_decode($brand, ENT_QUOTES, 'UTF-8'));
                $xml->endElement(); // brand

                $xml->endElement(); // brands
            }

            $xml->endElement(); // End <product_ids>

            $xml->startElement('product_name'); // Start <product_name>
            $xml->writeCData($review['product_name']); // Product name
            $xml->endElement(); // End <product_name>

            $xml->startElement('product_url'); // Start <product_url>
            $xml->writeCData(str_replace('&amp;', '&', $product_link)); // Product URL
            $xml->endElement(); // End <product_url>

            $xml->endElement(); // End <product>

            $xml->endElement(); // End <products>

            $xml->endElement(); // End <review>
        }

        $xml->endElement(); // End <reviews>

        $xml->endElement(); // End <feed>

        $xml->endDocument(); // End XML document <feed>

        $this->config->set('config_language_id', $old_language_id);

        $this->response->addHeader('Content-Type: application/xml');
        $this->response->setOutput($xml->outputMemory());
    }
}
