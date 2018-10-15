<?php if( !defined('WPINC') ) die;
/**
 * Leyka_Sberbank_Gateway class
 */

class Leyka_Sberbank_Gateway extends Leyka_Gateway {

    protected static $_instance; // Gateway is always a singleton

    protected function _set_options_defaults() {

        if($this->_options) // Create Gateway options, if needed
            return;

        $this->_options = array(
            'sberbank_redirect_page' => array(
                'type' => 'select',
                'default' => leyka_get_default_success_page(),
                'title' => __('Page to redirect a donor after a donation', 'leyka'),
                'description' => __('Select a page for donor to redirect to after he has acquired a sberbank.', 'leyka'),
                'required' => 0, // 1 if field is required, 0 otherwise
                'placeholder' => '', // For text fields
                'length' => '', // For text fields
                'list_entries' => leyka_get_pages_list(),
                'validation_rules' => array(), // List of regexp?..
            ),
        );
    }
    
    protected function _set_attributes() {

        $this->_id = 'sberbank';
        $this->_title = __('Sberbank', 'leyka');
        $this->_docs_link = '//leyka.te-st.ru/docs/nastrojka-lejki/';
    }

    protected function _initialize_pm_list() {
        if(empty($this->_payment_methods['sberbank_order'])) {
            $this->_payment_methods['sberbank_order'] = Leyka_Sberbank_Order::get_instance();
        }
    }

    public function process_form($gateway_id, $pm_id, $donation_id, $form_data) {

        header('HTTP/1.1 200 OK');
        header('Content-Type: text/html; charset=utf-8');

        $campaign = new Leyka_Campaign($form_data['leyka_campaign_id']);
        $sberbank_html = str_replace(
            array(
                '#BACK_TO_DONATION_FORM_TEXT#',
                '#PRINT_THE_QUITTANCE_TEXT#',
                '#QUITTANCE_RECEIVED_TEXT#',
                '#SUCCESS_URL#',
                '#PAYMENT_COMMENT#',
                '#PAYER_NAME#',
                '#RECEIVER_NAME#',
                '#SUM#',
                '#INN#',
                '#KPP#',
                '#ACC#',
                '#RECEIVER_BANK_NAME#',
                '#BIC#',
                '#CORR#',
            ),
            array( // Form field values
                __('Return to the donation form', 'leyka'),
                __('Print the sberbank', 'leyka'),
                __("OK, I've received the sberbank", 'leyka'),
                get_permalink(leyka_options()->opt('sberbank_redirect_page')),
                $campaign->payment_title." (№ $donation_id)",
                $form_data['leyka_donor_name'],
                leyka_options()->opt('org_full_name'),
                (int)$form_data['leyka_donation_amount'],
                leyka_options()->opt('org_inn'),
                leyka_options()->opt('org_kpp'),
                leyka_options()->opt('org_bank_account'),
                leyka_options()->opt('org_bank_name'),
                leyka_options()->opt('org_bank_bic'),
                leyka_options()->opt('org_bank_corr_account'),
            ),
            $this->_payment_methods[$pm_id]->get_sberbank_html()
        );

        for($i=0; $i<10; $i++) {
            $sberbank_html = str_replace("#INN_$i#", substr(leyka_options()->opt('org_inn'), $i, 1), $sberbank_html);
        }
        for($i=0; $i<20; $i++) {
            $sberbank_html = str_replace(
                "#ACC_$i#", substr(leyka_options()->opt('org_bank_account'), $i, 1), $sberbank_html
            );
        }
        for($i=0; $i<9; $i++) {
            $sberbank_html = str_replace(
                "#BIC_$i#", substr(leyka_options()->opt('org_bank_bic'), $i, 1), $sberbank_html
            );
        }
        for($i=0; $i<20; $i++) {
            $sberbank_html = str_replace(
                "#CORR_$i#", substr(leyka_options()->opt('org_bank_corr_account'), $i, 1), $sberbank_html
            );
        }

        die($sberbank_html);
    }

    /** Quittance don't use any specific redirects, so this method is empty. */
    public function submission_redirect_url($current_url, $pm_id) {
        return home_url('/leyka-process-donation');
    }
    
    /** Quittance don't have some form data to send to the gateway site */
    public function submission_form_data($form_data_vars, $pm_id, $donation_id) {
        return $form_data_vars;
    }

    /** Quittance don't have any of gateway service calls */
    public function _handle_service_calls($call_type = '') {}

    public function get_gateway_response_formatted(Leyka_Donation $donation) {
        return array();
    }

    /** Quittance don't use any specific fields, so this method is empty. */
    public function log_gateway_fields($donation_id) {
    }
}

class Leyka_Sberbank_Order extends Leyka_Payment_Method {

    private $_sberbank_html = '';

    protected static $_instance;

    protected function _set_attributes() {

        $this->_sberbank_html = file_get_contents(LEYKA_PLUGIN_DIR.'gateways/sberbank/bank_order.html');

        $this->_id = 'acquiring';
        $this->_gateway_id = 'sberbank';

        $this->_label_backend = __('Bank order sberbank', 'leyka');
        $this->_label = __('Bank order sberbank', 'leyka');

        $this->_icons = apply_filters('leyka_icons_'.$this->_gateway_id.'_'.$this->_id, array(
            LEYKA_PLUGIN_BASE_URL.'gateways/sberbank/icons/sber_s.png',
        ));

        $this->_submit_label = __('Get bank order sberbank', 'leyka');

        $this->_supported_currencies = array('rur');

        $this->_default_currency = 'rur';

	    // We should always redirect donors, even on ajax-based templates:
//        $this->_processing_type = 'redirect';
        $this->_ajax_without_form_submission = true;

    }

    protected function _set_options_defaults() {

        if($this->_options) {
            return;
        }

        $this->_options = array(
            $this->full_id.'_description' => array(
                'type' => 'html',
                'default' => __('Bank order payment allows you to make a donation through any bank. You can print out a bank order paper and bring it to the bank to make a payment.', 'leyka'),
                'title' => __('Bank order payment description', 'leyka'),
                'description' => __('Please, enter Bank order description that will be shown to the donor when this payment method will be selected to make a donation.', 'leyka'),
                'required' => 0,
                'validation_rules' => array(),
            ),
        );
    }

    function get_sberbank_html() {
        return $this->_sberbank_html;
    }
}

function leyka_add_gateway_sberbank() { // Use named function to leave a possibility to remove/replace it on the hook
    leyka_add_gateway(Leyka_Sberbank_Gateway::get_instance());
}
add_action('leyka_init_actions', 'leyka_add_gateway_sberbank');