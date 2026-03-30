<?php
class ControllerExtensionFeedPsProductReviewFeed extends Controller
{
    /**
     * @var string The support email address.
     */
    const EXTENSION_EMAIL = 'support@playfulsparkle.com';

    /**
     * @var string The URL to the support website.
     */
    const SUPPORT_URL = 'https://support.playfulsparkle.com';

    /**
     * @var string The GitHub repository URL of the extension.
     */
    const GITHUB_REPO_URL = 'https://github.com/playfulsparkle/oc3_product_review_feed';

    private $error = array();

    /**
     * Displays the Google Product Review Feed settings page.
     *
     * This method loads the necessary language file, sets the title of the page,
     * and prepares the data for the view. It also generates the breadcrumbs for
     * navigation and retrieves configuration settings for the sitemap.
     *
     * @return void
     */
    public function index()
    {
        $this->load->language('extension/feed/ps_product_review_feed');

        $this->document->setTitle($this->language->get('heading_title'));

        $this->load->model('setting/setting');

        if (isset($this->request->get['store_id'])) {
            $store_id = (int) $this->request->get['store_id'];
        } else {
            $store_id = 0;
        }

        if (($this->request->server['REQUEST_METHOD'] == 'POST') && $this->validate()) {
            $this->model_setting_setting->editSetting('feed_ps_product_review_feed', $this->request->post, $store_id);

            $this->session->data['success'] = $this->language->get('text_success');

            $this->response->redirect($this->url->link('marketplace/extension', 'user_token=' . $this->session->data['user_token'] . '&type=feed', true));
        }

		if (isset($this->error['warning'])) {
			$data['error_warning'] = $this->error['warning'];
		} else {
			$data['error_warning'] = '';
		}

        if (isset($this->error['merchant_id'])) {
            $data['error_merchant_id'] = $this->error['merchant_id'];
        } else {
            $data['error_merchant_id'] = '';
        }


        $data['breadcrumbs'] = array();

        $data['breadcrumbs'][] = array(
            'text' => $this->language->get('text_home'),
            'href' => $this->url->link('common/dashboard', 'user_token=' . $this->session->data['user_token'], true)
        );

        $data['breadcrumbs'][] = array(
            'text' => $this->language->get('text_extension'),
            'href' => $this->url->link('marketplace/extension', 'user_token=' . $this->session->data['user_token'] . '&type=feed', true)
        );

        $data['breadcrumbs'][] = array(
            'text' => $this->language->get('heading_title'),
            'href' => $this->url->link('extension/feed/ps_product_review_feed', 'user_token=' . $this->session->data['user_token'] . '&store_id=' . $store_id, true)
        );

        $data['action'] = $this->url->link('extension/feed/ps_product_review_feed', 'user_token=' . $this->session->data['user_token'] . '&store_id=' . $store_id, true);

        $data['cancel'] = $this->url->link('marketplace/extension', 'user_token=' . $this->session->data['user_token'] . '&type=feed', true);

        $data['user_token'] = $this->session->data['user_token'];

        if (isset($this->request->post['feed_ps_product_review_feed_status'])) {
            $data['feed_ps_product_review_feed_status'] = (bool) $this->request->post['feed_ps_product_review_feed_status'];
        } else {
            $data['feed_ps_product_review_feed_status'] = (bool) $this->model_setting_setting->getSettingValue('feed_ps_product_review_feed_status', $store_id);
        }

        if (isset($this->request->post['feed_ps_product_review_feed_login'])) {
            $data['feed_ps_product_review_feed_login'] = $this->request->post['feed_ps_product_review_feed_login'];
        } else {
            $data['feed_ps_product_review_feed_login'] = $this->model_setting_setting->getSettingValue('feed_ps_product_review_feed_login', $store_id);
        }

        if (isset($this->request->post['feed_ps_product_review_feed_password'])) {
            $data['feed_ps_product_review_feed_password'] = $this->request->post['feed_ps_product_review_feed_password'];
        } else {
            $data['feed_ps_product_review_feed_password'] = $this->model_setting_setting->getSettingValue('feed_ps_product_review_feed_password', $store_id);
        }

        if (isset($this->request->post['feed_ps_product_review_feed_opt_in_integration'])) {
            $data['feed_ps_product_review_feed_opt_in_integration'] = (bool) $this->request->post['feed_ps_product_review_feed_opt_in_integration'];
        } else {
            $data['feed_ps_product_review_feed_opt_in_integration'] = (bool) $this->model_setting_setting->getSettingValue('feed_ps_product_review_feed_opt_in_integration', $store_id);
        }

        if (isset($this->request->post['feed_ps_product_review_feed_badge_integration'])) {
            $data['feed_ps_product_review_feed_badge_integration'] = (bool) $this->request->post['feed_ps_product_review_feed_badge_integration'];
        } else {
            $data['feed_ps_product_review_feed_badge_integration'] = (bool) $this->model_setting_setting->getSettingValue('feed_ps_product_review_feed_badge_integration', $store_id);
        }

        if (isset($this->request->post['feed_ps_product_review_feed_merchant_id'])) {
            $data['feed_ps_product_review_feed_merchant_id'] = $this->request->post['feed_ps_product_review_feed_merchant_id'];
        } else {
            $data['feed_ps_product_review_feed_merchant_id'] = $this->model_setting_setting->getSettingValue('feed_ps_product_review_feed_merchant_id', $store_id);
        }

        $this->load->model('localisation/language');

        $languages = $this->model_localisation_language->getLanguages();

        $data['languages'] = $languages;

        $data['store_id'] = $store_id;

        $data['stores'] = array();

        $data['stores'][] = array(
            'store_id' => 0,
            'name' => $this->config->get('config_name') . '&nbsp;' . $this->language->get('text_default'),
            'href' => $this->url->link('extension/feed/ps_product_review_feed', 'user_token=' . $this->session->data['user_token'] . '&store_id=0'),
        );

        $this->load->model('setting/store');

        $stores = $this->model_setting_store->getStores();

        $store_url = HTTP_CATALOG;

        foreach ($stores as $store) {
            $data['stores'][] = array(
                'store_id' => $store['store_id'],
                'name' => $store['name'],
                'href' => $this->url->link('extension/feed/ps_product_review_feed', 'user_token=' . $this->session->data['user_token'] . '&store_id=' . $store['store_id']),
            );

            if ((int) $store['store_id'] === $store_id) {
                $store_url = $store['url'];
            }
        }

        $data['review_feed_urls'] = array();

        foreach ($languages as $language) {
            $data['review_feed_urls'][$language['language_id']] = rtrim($store_url, '/') . '/index.php?route=extension/feed/ps_product_review_feed&language=' . $language['code'];
        }

        $data['text_contact'] = sprintf($this->language->get('text_contact'), self::SUPPORT_URL, self::GITHUB_REPO_URL, self::EXTENSION_EMAIL);

        $data['header'] = $this->load->controller('common/header');
        $data['column_left'] = $this->load->controller('common/column_left');
        $data['footer'] = $this->load->controller('common/footer');

        $this->response->setOutput($this->load->view('extension/feed/ps_product_review_feed', $data));
    }

    protected function validate()
    {
        if (!$this->user->hasPermission('modify', 'extension/feed/ps_product_review_feed')) {
            $this->error['warning'] = $this->language->get('error_permission');
        }

        if (!$this->error && !isset($this->request->post['store_id'])) {
            $this->error['warning'] = $this->language->get('error_store_id');
        }

        if (!$this->error) {
            if (empty($this->request->post['feed_ps_product_review_feed_merchant_id'])) {
                $this->error['merchant_id'] = $this->language->get('error_merchant_id');
            } elseif (preg_match('/^\d{7,10}$/', strtoupper($this->request->post['feed_ps_product_review_feed_merchant_id'])) !== 1) {
                $this->error['merchant_id'] = $this->language->get('error_merchant_id_invalid');
            }
        }

        return !$this->error;
    }

    public function install()
    {
        $this->load->model('setting/setting');

        $data = array(
            'feed_ps_product_review_feed_badge_integration' => 0,
            'feed_ps_product_review_feed_login' => '',
            'feed_ps_product_review_feed_merchant_id' => '',
            'feed_ps_product_review_feed_opt_in_integration' => 0,
            'feed_ps_product_review_feed_password' => '',
            'feed_ps_product_review_feed_status' => 0,
        );

        $this->model_setting_setting->editSetting('feed_ps_product_review_feed', $data);
    }

    public function uninstall()
    {

    }
}
