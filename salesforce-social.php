<?php
/*
Plugin Name: Salesforce Social
Plugin URI: http://www.cornersoftware.co.uk/?page_id=93
Description: Salesforce to Wordpress / Buddypress Integration Suite
Author: Pete Ryan - Corner Software Ltd
Version: 1.0.4
Author URI: http://www.cornersoftware.co.uk
*/

/*
 * @todo
 *   Thanks to Pressography.com for plugin template - http://www.viddler.com/explore/Pressography/videos/2/869.016/
 * Refs:-
 *  Implement whitelist checks - ref http://www.chiragmehta.info/chirag/2010/08/17/what-salesforce-com-network-ip-addresses-do-i-need-to-whitelist/
 *   http://forums.sforce.com/t5/Apex-Code-Development/What-is-Salesforce-IP-address-range/m-p/165449
 *   Get server ip - https://www.ippages.com/?site=na7.salesforce.com
 *   http://en.wikipedia.org/wiki/Classless_Inter-Domain_Routing
 *   204.14.232.0/25,204.14.233.0/25,204.14.234.0/25,204.14.235.0/25
 *   http://www.subnet-calculator.com/cidr.php
 *
 */

/**
* Guess the wp-content and plugin urls/paths
*/
if ( ! defined( 'WP_CONTENT_URL' ) )
      define( 'WP_CONTENT_URL', get_option( 'siteurl' ) . '/wp-content' );
if ( ! defined( 'WP_CONTENT_DIR' ) )
      define( 'WP_CONTENT_DIR', ABSPATH . 'wp-content' );
if ( ! defined( 'WP_PLUGIN_URL' ) )
      define( 'WP_PLUGIN_URL', WP_CONTENT_URL. '/plugins' );
if ( ! defined( 'WP_PLUGIN_DIR' ) )
      define( 'WP_PLUGIN_DIR', WP_CONTENT_DIR . '/plugins' );


if (!class_exists('SFSoc')) {

    require_once "XmlHandler.php";
    
    class SFSoc {

        const API_NAME = 'Salesforce Social';
        const API_VERSION = '0.0.1';
        const API_ROOT  = 'sfs_api';

        const OPTIONS_NAME = 'SFSoc_options'; // The options string name for this plugin
        const WEB_TO_LEAD_CAPTCHA_KEY_OPTIONS_NAME = 'SFSoc_w2le'; // The options string name for this plugin
        const WEB_TO_LEAD_FORM_OPTIONS_NAME = 'SFSoc_w2lf'; // The options string name for this plugin
        const LOCALIZATION_DOMAIN = 'SFSoc'; // Domain used for localization
        const MAX_ROWS = 100; // Domain used for localization

        const CODE_SUCCESS = '100';
        const CODE_FAILURE = '200';
        const CODE_ERROR = '300';

        const MIN_WP_VER = '3.0.0';
        const MIN_BP_VER = '1.2.7';

        const WEB_TO_LEAD_STAGE_EMPTY = 1;
        const WEB_TO_LEAD_STAGE_ANALYSED = 2;
        const WEB_TO_LEAD_STAGE_SANTIZED = 3;
        const WEB_TO_LEAD_STAGE_READY = 4;

        /**
        * @var string $pluginurl The path to this plugin
        */
        var $thispluginurl = '';

        /**
        * @var string $pluginurlpath The path to this plugin
        */
        var $thispluginpath = '';

        /**
        * @var array $options Stores the options for this plugin
        */
        var $options = array();

        /**
        * @var string $transaction Stores the validated/authorised current api action/request
        */
        var $transaction = '';

        /**
        * @var string $statusCode Stores a string/numeric code to indicate transaction processing status 100|200|300 = success|failure|error
        */
        var $statusCode = '';

        /**
        * @var string $statusDescription Stores a string related to the $statusCode numeric code to indicate transaction processing status 100|200|300 = success|failure|error
        */
        var $statusDescription = '';

        /**
        * @var string $statusMessage Stores a status message to be returned to the caller
        */
        var $statusMessage = '';

        /**
        * @var string $dataIn Stores the request
        */
        var $dataIn = '';

        var $webToLeadForm = '';
        var $webToLeadForm_Action = '';
        var $webToLeadForm_OID = '';
        var $webToLeadInputFields = array();
        var $webToLeadMandInputFields = array();


        //Class Functions
        /**
        * PHP 4 Compatible Constructor
        */
        function SFSoc(){$this->__construct();}

        /**
        * PHP 5 Constructor
        */
        function __construct(){

            //Language Setup
            $locale = get_locale();
            $mo = dirname(__FILE__) . "/languages/" . SFSoc::LOCALIZATION_DOMAIN . "-".$locale.".mo";
            load_textdomain(SFSoc::LOCALIZATION_DOMAIN, $mo);

            //"Constants" setup
            $this->thispluginurl = WP_PLUGIN_URL . '/' . dirname(plugin_basename(__FILE__)).'/';
            $this->thispluginpath = WP_PLUGIN_PATH . '/' . dirname(plugin_basename(__FILE__)).'/';

            //Initialize the options
            //This is REQUIRED to initialize the options when the plugin is loaded!
            $this->getOptions();


            //Actions
            add_action("admin_menu", array(&$this,"admin_menu_link"));


            //Widget Registration Actions
            add_action('plugins_loaded', array(&$this,'register_widgets'));

            /*
            add_action("wp_head", array(&$this,"add_css"));
            add_action('wp_print_scripts', array(&$this, 'add_js'));
            */

            //Filters
            /*
            add_filter('the_content', array(&$this, 'filter_content'), 0);
            */

            /*
             * API Interceptor
             */
            // Ensure that the request is parsed before any WP front-end stuff, but after the core of WP is loaded
            add_action('wp', array(&$this, 'sfs_api_listener'), 1);

            add_shortcode('webtolead', array(&$this, 'webToLead'));

        }

        /**
        * Retrieves the plugin options from the database.
        */


        function getOptions() {
            if (!$theOptions = get_option(SFSoc::OPTIONS_NAME)) {
                $theOptions = array(
                    'SFSoc_whitelist'=>'204.14.232.0/25,204.14.233.0/25,204.14.234.0/25,204.14.235.0/25',
                    'SFSoc_https'=>true,
                    'SFSoc_w2l_success'=>__('Thank you for submitting your information.', SFSoc::LOCALIZATION_DOMAIN),
                    'SFSoc_w2l_failure'=>__('There was a problem saving your information. Please contact the site administrator.', SFSoc::LOCALIZATION_DOMAIN),
                    'SFSoc_w2ls'=>'Wordpress/Buddypress',
                    'SFSoc_w2l_stage'=> SFSoc::WEB_TO_LEAD_STAGE_EMPTY,
                    'SFSoc_w2l_cbgc'=>'128',
                    'SFSoc_w2l_cbgcl'=>'120',
                    'SFSoc_w2l_cbgch'=>'136',
                    'SFSoc_w2l_ctcl'=>'230',
                    'SFSoc_w2l_ctch'=>'240',
                    'SFSoc_w2l_cba'=>'on',
                    'SFSoc_w2l_carl'=>'on'
                    );
                update_option(SFSoc::OPTIONS_NAME, $theOptions);
            }
            $this->options = $theOptions;
            $this->webToLeadForm = get_option(SFSoc::WEB_TO_LEAD_FORM_OPTIONS_NAME);

            //!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!
            //There is no return here, because you should use the $this->options variable!!!
            //!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!
        }

        /**
        * Saves the admin options to the database.
        */
        function saveAdminOptions(){
            $retVal1 = false;
            $retVal2 = false;
            $retVal3 = false;

            /** SET NEW KEY FOR CAPTCHA - separate from ,other options becuase it will be read frequently and we don't want to read loads of other stuff with it**/
            $retVal1 = update_option(SFSoc::WEB_TO_LEAD_FORM_OPTIONS_NAME, SFSoc::$this->webToLeadForm);

            /** Save the details of the web to lead form - separate from other options becuase it may be big! **/
            $retVal2 = update_option(SFSoc::WEB_TO_LEAD_CAPTCHA_KEY_OPTIONS_NAME, SFSoc::getNewEncodeKey());

            $retVal3 = update_option(SFSoc::OPTIONS_NAME, $this->options);

            if ($retVal1 && $retVal2 && $retVal3) {
                return true;
            } else {
                return false;
            }
        }

        /**
        * @desc Adds the options subpanel
        */
        function admin_menu_link() {
            //If you change this from add_options_page, MAKE SURE you change the filter_plugin_actions function (below) to
            //reflect the page filename (ie - options-general.php) of the page your plugin is under!
            add_options_page('Salesforce Social', 'Salesforce Social', 10, basename(__FILE__), array(&$this,'admin_options_page'));
            add_filter( 'plugin_action_links_' . plugin_basename(__FILE__), array(&$this, 'filter_plugin_actions'), 10, 2 );
        }

        /**
        * @desc Adds the Settings link to the plugin activate/deactivate page
        */
        function filter_plugin_actions($links, $file) {
           //If your plugin is under a different top-level menu than Settiongs (IE - you changed the function above to something other than add_options_page)
           //Then you're going to want to change options-general.php below to the name of your top-level page
           $settings_link = '<a href="options-general.php?page=' . basename(__FILE__) . '">' . __('Settings') . '</a>';
           array_unshift( $links, $settings_link ); // before other links

           return $links;
        }

        /**
        * @desc Adds settings/options page
        */
        function admin_options_page() {

            if($_POST['SFSoc_sanitize'] || $_POST['SFSoc_analyse'] || $_POST['SFSoc_save'] || $_POST['SFSoc_w2lClear']){
                

                if (! wp_verify_nonce($_POST['_wpnonce'], 'SFSoc-update-options') ) die(__('There was a problem with the data you posted. Please go back and try again.', SFSoc::LOCALIZATION_DOMAIN));

                $pwdindb = $this->options['SFSoc_password'];
                $pwdentered = $_POST['SFSoc_password'];
                $pwdventered = $_POST['SFSoc_vpassword'];
                $newpassword = $pwdentered;

                $whitel = $_POST['SFSoc_whitelist'];
                if (empty($whitel)) {
                    $whitel = '204.14.232.0/25,204.14.233.0/25,204.14.234.0/25,204.14.235.0/25';
                }

                // If mandatory fields have been selected then CSS is required.
                $this->webToLeadMandInputFields = $_POST['webToLeadMandInputFields'];
                if ($_POST['SFSoc_sanitize'] && count($this->webToLeadMandInputFields) > 0 && !$_POST['SFSoc_w2l_css']=='on') {
                    die(__('CSS must be selected for validation to work. Please go back and try again. (Edit the default CSS included by the sanitization process to meet your needs.)', SFSoc::LOCALIZATION_DOMAIN));
                }



                // If the api is enabled then validate password etc
                $this->options['SFSoc_enable_api'] = ($_POST['SFSoc_enable_api']=='on')?true:false;
                if ($this->options['SFSoc_enable_api']==true) {
                    if (empty($pwdentered)) {
                        die(__('Password must be entered and be more than 8 charcters long. Please go back and try again.', SFSoc::LOCALIZATION_DOMAIN));
                    }
                    if (strlen(trim($pwdentered))<9) {
                        die(__('Password must be entered and be more than 8 charcters long. Please go back and try again.', SFSoc::LOCALIZATION_DOMAIN));
                    }
                    if (empty($pwdventered)) {
                        die(__('Password must be verified / entered twice. Please go back and try again.', SFSoc::LOCALIZATION_DOMAIN));
                    }
                    if ($pwdentered != $pwdventered) {
                        die(__('Password must be verified / entered the same twice. Please go back and try again.', SFSoc::LOCALIZATION_DOMAIN));
                    }
                    if (empty($pwdindb) || ($pwdentered <> $pwdindb)) {
                        $newpassword = wp_hash_password(trim($pwdentered));
                    }
                }
                $this->options['SFSoc_user'] = $_POST['SFSoc_user'];
                $this->options['SFSoc_password'] = $newpassword;
                $this->options['SFSoc_bp_xprofiles'] = ($_POST['SFSoc_bp_xprofiles']=='on')?true:false;
                $this->options['SFSoc_https'] = ($_POST['SFSoc_https']=='on')?true:false;
                $this->options['SFSoc_whitelist'] = $whitel;

                $this->options['SFSoc_w2l_captcha'] = ($_POST['SFSoc_w2l_captcha']=='on')?true:false;
                $this->options['SFSoc_w2l_css'] = ($_POST['SFSoc_w2l_css']=='on')?true:false;

                $this->options['SFSoc_w2l_success'] = $_POST['SFSoc_w2l_success'];
                $this->options['SFSoc_w2l_failure'] = $_POST['SFSoc_w2l_failure'];
                $this->options['SFSoc_w2ls'] = $_POST['SFSoc_w2ls'];

                // Default captcha colours if not entered
                $colent = $_POST['SFSoc_w2l_cbgc'];
                if (empty($colent)) {
                    $colent = '128';
                }
                $this->options['SFSoc_w2l_cbgc'] = $colent;

                $colent = $_POST['SFSoc_w2l_cbgcl'];
                if (empty($colent)) {
                    $colent = '128';
                }
                $this->options['SFSoc_w2l_cbgcl'] = $colent;

                $colent = $_POST['SFSoc_w2l_cbgch'];
                if (empty($colent)) {
                    $colent = '128';
                }
                $this->options['SFSoc_w2l_cbgch'] = $colent;

                $colent = $_POST['SFSoc_w2l_ctcl'];
                if (empty($colent)) {
                    $colent = '250';
                }
                $this->options['SFSoc_w2l_ctcl'] = $colent;

                $colent = $_POST['SFSoc_w2l_ctch'];
                if (empty($colent)) {
                    $colent = '255';
                }
                $this->options['SFSoc_w2l_ctch'] = $colent;


                /** WEB TO LEAD **/
                $this->webToLeadForm = $_POST[SFSoc::WEB_TO_LEAD_FORM_OPTIONS_NAME];
                $this->webToLeadForm = stripslashes($this->webToLeadForm);
                if(empty($this->webToLeadForm)) {
                    $this->options['SFSoc_w2l_stage'] = SFSoc::WEB_TO_LEAD_STAGE_EMPTY;
                } else {

                    if($_POST['SFSoc_w2lClear']) {
                        $this->webToLeadForm = '';
                        $this->options['SFSoc_w2l_stage'] = SFSoc::WEB_TO_LEAD_STAGE_EMPTY;
                    } else if($_POST['SFSoc_sanitize']) {
                        $this->sanitizeWebToLeadForm($this->options['SFSoc_w2l_captcha'], $this->options['SFSoc_w2l_css']);
                        $this->options['SFSoc_w2l_stage'] = SFSoc::WEB_TO_LEAD_STAGE_SANTIZED;

                    } else if($_POST['SFSoc_analyse'] && !empty($this->webToLeadForm)) {
                        $this->analyseWebToLeadForm();
                        $this->options['SFSoc_w2l_stage'] = SFSoc::WEB_TO_LEAD_STAGE_ANALYSED;
                    } else {
                        $this->options['SFSoc_w2l_stage'] = SFSoc::WEB_TO_LEAD_STAGE_READY;
                    }

                    $this->webToLeadForm = htmlentities($this->webToLeadForm);
                }


                if (empty($this->webToLeadForm_Action)) {
                    $this->webToLeadForm_Action = $_POST['SFSoc_w2l_action'];
                }
                $this->options['SFSoc_w2l_action'] = $this->webToLeadForm_Action;


                if (empty($this->webToLeadForm_OID)) {
                    $this->webToLeadForm_OID = $_POST['SFSoc_w2l_org'];
                }
                $this->options['SFSoc_w2l_org'] = $this->webToLeadForm_OID;

                $this->saveAdminOptions();

                echo '<div><p>'.__('Success! Your changes were sucessfully saved!', SFSoc::LOCALIZATION_DOMAIN).'</p></div>';
            }

            // check box
            $chkEnableApi = '';
            if ($this->options['SFSoc_enable_api']==true) {
                $chkEnableApi = 'checked';
            }

            // check box
            $chkBPXProfileAdmin = '';
            if ($this->options['SFSoc_bp_xprofiles']==true) {
                $chkBPXProfileAdmin = 'checked';
            }

            // check box
            $chkHttps = '';
            if ($this->options['SFSoc_https']==true) {
                $chkHttps = 'checked';
            }

            // check box
            $chkSFSoc_w2l_cba = '';
            if ($this->options['SFSoc_w2l_cba']==true) {
                $chkSFSoc_w2l_cba = 'checked';
            }

            // check box
            $chkSFSoc_w2l_carl = '';
            if ($this->options['SFSoc_w2l_carl']==true) {
                $chkSFSoc_w2l_carl = 'checked';
            }

            // check boxes - CSS and Captcha options - Show these settings defaulted to on / true - should not be settings
            $chkAddCSS = '';
            $chkAddCaptcha = '';
            if ($_POST['SFSoc_analyse']) {
                $chkAddCaptcha = 'checked';
                $chkAddCSS = 'checked';
            } else {
                if ($this->options['SFSoc_w2l_captcha']==true) {
                    $chkAddCaptcha = 'checked';
                }

                if ($this->options['SFSoc_w2l_css']==true) {
                    $chkAddCSS = 'checked';
                }
            }

            global $wp_version;
            $ver_exit_msg = array();
            $verfixneeded = false;

            if (version_compare($wp_version,SFSoc::MIN_WP_VER,"<")) {
                $ver_exit_msg[] = 'Salesforce Social suggests Wordpress version ';
                $ver_exit_msg[] = SFSoc::MIN_WP_VER;
                $ver_exit_msg[] = ' or newer. Please update!';
                $verfixneeded = true;
            }

            if (defined('BP_VERSION')) {
                if (version_compare(BP_VERSION, SFSoc::MIN_BP_VER,'<')) {
                    $ver_exit_msg[] = '<br>';
                    $ver_exit_msg[] = __('Salesforce Social suggests Buddypress version ', SFSoc::LOCALIZATION_DOMAIN);
                    $ver_exit_msg[] = SFSoc::MIN_BP_VER;
                    $ver_exit_msg[] = __(' or newer. Please update!', SFSoc::LOCALIZATION_DOMAIN);
                    $verfixneeded = true;
                }
            }
            /*
            if ($verfixneeded) {
		exit (implode('',$ver_exit_msg));
            }
             *
             */

?>
                <div class="wrap">
                <?php if($verfixneeded==TRUE) : ?>
                    <h4><?php echo(implode('',$ver_exit_msg)) ?></h4>
                <?php endif; ?>

                <h2>Salesforce Social</h2>
                <h3><?php _e('Salesforce / Wordpress / Buddypress Integration', SFSoc::LOCALIZATION_DOMAIN); ?></h3>
                <p><?php _e('Salesforce Membership Management Systems from Corner Software Ltd', SFSoc::LOCALIZATION_DOMAIN); ?></p>
                <h4><?php _e('Web to Lead Form', SFSoc::LOCALIZATION_DOMAIN); ?></h4>
                    <p><?php _e('1. Log into Salesforce and navigate to Setup->App Setup->Customize->Leads->Web-to-Lead.', SFSoc::LOCALIZATION_DOMAIN); ?>
                    <br><?php _e('2. In Salesforce click "Create Web-to-Lead Form" and follow on screen directions, finally click "Generate" and copy the entire HTML generated.', SFSoc::LOCALIZATION_DOMAIN); ?>
                    <br><?php _e('3. In this screen, click "Clear" and then paste the entire HTML generated by step 2 into the box below then click "Analyse".', SFSoc::LOCALIZATION_DOMAIN); ?>
                    <br><?php _e('4. Select "Adding Captcha Validation" to reduce spam or leave it blank to implement your own validation scheme.', SFSoc::LOCALIZATION_DOMAIN); ?>
                    <br><?php _e('5. Select "Adding CSS Styling" or leave it blank to implement your own styling.', SFSoc::LOCALIZATION_DOMAIN); ?>
                    <br><small>    <?php _e('(Note that CSS Styling must be selected for validation to work. Edit the form after sanitized to incorporate your own styling.)', SFSoc::LOCALIZATION_DOMAIN); ?></small>
                    <br><?php _e('6. Select fields to be regarded as mandatory.', SFSoc::LOCALIZATION_DOMAIN); ?>
                    <br><?php _e('7. Click "Sanitize".', SFSoc::LOCALIZATION_DOMAIN); ?>
                    <br><?php _e('8. Enter [webtolead] in your Wordpress/Buddypress content to display the Web-to-Lead form.', SFSoc::LOCALIZATION_DOMAIN); ?>
                    <br><?php _e('9. Test to confirm that submitted forms result in Leads appearing in Salesforce.com', SFSoc::LOCALIZATION_DOMAIN); ?>
                    </p>

                <form method="post" id="SFSoc_options">
                <?php wp_nonce_field('SFSoc-update-options'); ?>
                    <table width="100%" cellspacing="2" cellpadding="5" class="form-table">
                        <tr valign="top">
                            <th width="33%" scope="row"><?php _e('Web-to-Lead Form:', SFSoc::LOCALIZATION_DOMAIN); ?></th>
                            <td>
                                <textarea name="<?php echo SFSoc::WEB_TO_LEAD_FORM_OPTIONS_NAME; ?>" id="<?php echo SFSoc::WEB_TO_LEAD_FORM_OPTIONS_NAME; ?>" rows="5" cols="75"><?php echo $this->webToLeadForm ;?></textarea>
                                <br>
                                <?php if($this->options['SFSoc_w2l_stage'] == SFSoc::WEB_TO_LEAD_STAGE_READY || $this->options['SFSoc_w2l_stage'] == SFSoc::WEB_TO_LEAD_STAGE_SANTIZED) : ?>
                                    <?php _e('Edit the text in the box above to fine tune your Web-to-Lead form.', SFSoc::LOCALIZATION_DOMAIN); ?>
                                    <br>
                                    <input type="submit" name="SFSoc_w2lClear" value="Clear"/>
                                <?php endif; ?>
                                <?php if($this->options['SFSoc_w2l_stage'] == SFSoc::WEB_TO_LEAD_STAGE_EMPTY) : ?>
                                    <?php _e('Copy the raw Web-to-lead form generated by Salesforce to the box above and click Analyse to proceed.', SFSoc::LOCALIZATION_DOMAIN); ?>
                                    <br>
                                    <input type="submit" name="SFSoc_w2lClear" value="Clear"/>
                                    <input type="submit" name="SFSoc_analyse" value="Analyse"/>
                                <?php endif; ?>
                                <?php if($this->options['SFSoc_w2l_stage'] == SFSoc::WEB_TO_LEAD_STAGE_ANALYSED) : ?>
                                    <br>
                                    &nbsp;<label for="SFSoc_w2l_captcha"><?php _e('Adding Captcha Validation', SFSoc::LOCALIZATION_DOMAIN); ?></label>&nbsp;<input type="checkbox" id="SFSoc_w2l_captcha" name="SFSoc_w2l_captcha" <?php echo $chkAddCaptcha ?>>
                                    &nbsp;<label for="SFSoc_w2l_css"><?php _e('Adding CSS Styling', SFSoc::LOCALIZATION_DOMAIN); ?></label>&nbsp;<input type="checkbox" id="SFSoc_w2l_css" name="SFSoc_w2l_css" <?php echo $chkAddCSS ?>>

                                    <br><br><?php _e('Madatory', SFSoc::LOCALIZATION_DOMAIN); ?>
                                    <br><select name="webToLeadMandInputFields[]" multiple="multiple" style="height: 10em;" size="5"><?php echo SFSoc::getOptionsHtml($this->webToLeadInputFields, $this->webToLeadMandInputFields, true); ?></select><br>
                                    <br><?php _e('Select mandatory fields in the above list and click Sanitize to proceed.', SFSoc::LOCALIZATION_DOMAIN); ?>
                                    <br>
                                    <input type="submit" name="SFSoc_w2lClear" value="Clear"/>
                                    <input type="submit" name="SFSoc_sanitize" value="Sanitize"/>
                                <?php endif; ?>

                            </td>
                        </tr>
                        <tr valign="top">
                            <th width="33%" scope="row"><?php _e('url:', SFSoc::LOCALIZATION_DOMAIN); ?></th>
                            <td><input name="SFSoc_w2l_action" type="text" id="SFSoc_w2l_action" size="75" value="<?php echo $this->options['SFSoc_w2l_action'] ;?>"/>
                            </td>
                        </tr>
                        <tr valign="top">
                            <th width="33%" scope="row"><?php _e('Organisation ID:', SFSoc::LOCALIZATION_DOMAIN); ?></th>
                            <td><input name="SFSoc_w2l_org" type="text" id="SFSoc_w2l_org" size="75" value="<?php echo $this->options['SFSoc_w2l_org'] ;?>"/>
                            </td>
                        </tr>
                        <tr valign="top">
                            <th width="33%" scope="row"><?php _e('Success Message:', SFSoc::LOCALIZATION_DOMAIN); ?></th>
                            <td><input name="SFSoc_w2l_success" type="text" id="SFSoc_w2l_success" size="45" value="<?php echo $this->options['SFSoc_w2l_success'] ;?>"/>
                        </td>
                        </tr>
                        <tr valign="top">
                            <th width="33%" scope="row"><?php _e('Failure Message:', SFSoc::LOCALIZATION_DOMAIN); ?></th>
                            <td><input name="SFSoc_w2l_failure" type="text" id="SFSoc_w2l_failure" size="45" value="<?php echo $this->options['SFSoc_w2l_failure'] ;?>"/>
                        </td>
                        </tr>
                        <tr valign="top">
                            <th width="33%" scope="row"><?php _e('Lead Source:', SFSoc::LOCALIZATION_DOMAIN); ?></th>
                            <td><input name="SFSoc_w2ls" type="text" id="SFSoc_w2ls" size="45" value="<?php echo $this->options['SFSoc_w2ls'] ;?>"/>
                        </td>
                        <tr valign="top">
                            <th width="33%" scope="row"><?php _e('Captcha Appearance:', SFSoc::LOCALIZATION_DOMAIN); ?></th>
                            <td>
                                    <?php _e('Background Colour:', SFSoc::LOCALIZATION_DOMAIN); ?>&nbsp;<input name="SFSoc_w2l_cbgc" type="text" id="SFSoc_w2l_cbgc" size="3" value="<?php echo $this->options['SFSoc_w2l_cbgc'] ;?>"/>
                                    &nbsp;<?php _e('Low Value:', SFSoc::LOCALIZATION_DOMAIN); ?>&nbsp;<input name="SFSoc_w2l_cbgcl" type="text" id="SFSoc_w2l_cbgcl" size="3" value="<?php echo $this->options['SFSoc_w2l_cbgcl'] ;?>"/>
                                    &nbsp;<?php _e('High Value:', SFSoc::LOCALIZATION_DOMAIN); ?>&nbsp;<input name="SFSoc_w2l_cbgch" type="text" id="SFSoc_w2l_cbgch" size="3" value="<?php echo $this->options['SFSoc_w2l_cbgch'] ;?>"/>
                                    <br><?php _e('Text Colour Low Value:', SFSoc::LOCALIZATION_DOMAIN); ?>&nbsp;<input name="SFSoc_w2l_ctcl" type="text" id="SFSoc_w2l_ctcl" size="3" value="<?php echo $this->options['SFSoc_w2l_ctcl'] ;?>"/>
                                    &nbsp;<?php _e('high value:', SFSoc::LOCALIZATION_DOMAIN); ?>&nbsp;<input name="SFSoc_w2l_ctch" type="text" id="SFSoc_w2l_ctch" size="3" value="<?php echo $this->options['SFSoc_w2l_ctch'] ;?>"/>
                                    <br><label for="SFSoc_w2l_cba"><?php _e('Add Coloured Areas to Background', SFSoc::LOCALIZATION_DOMAIN); ?></label>&nbsp;<input type="checkbox" id="SFSoc_w2l_cba" name="SFSoc_w2l_cba" <?php echo $chkSFSoc_w2l_cba ?>>
                                    <br><label for="SFSoc_w2l_carl"><?php _e('Add Random Lines', SFSoc::LOCALIZATION_DOMAIN); ?></label>&nbsp;<input type="checkbox" id="SFSoc_w2l_carl" name="SFSoc_w2l_carl" <?php echo $chkSFSoc_w2l_carl ?>>
                            </td>
                        </tr>
                        <tr>
                            <th colspan=2><input type="submit" name="SFSoc_save" value="Save" /></th>
                        </tr>
                </table>
                <h4>Wordpress / Buddypress REST API</h4>
                    <table width="100%" cellspacing="2" cellpadding="5" class="form-table">
                        <tr valign="top">
                            <th><label for="SFSoc_enable_api"><?php _e('Enable:', SFSoc::LOCALIZATION_DOMAIN); ?></label></th>
                            <td><input type="checkbox" id="SFSoc_enable_api" name="SFSoc_enable_api" <?php echo $chkEnableApi ?>></td>
                        </tr>
                        <tr valign="top">
                            <th width="33%" scope="row"><?php _e('User Name:', SFSoc::LOCALIZATION_DOMAIN); ?></th>
                            <td><input name="SFSoc_user" type="text" id="SFSoc_user" size="45" value="<?php echo $this->options['SFSoc_user'] ;?>"/>
                        </td>
                        </tr>
                        <tr valign="top">
                            <th width="33%" scope="row"><?php _e('Password:', SFSoc::LOCALIZATION_DOMAIN); ?></th>
                            <td><input name="SFSoc_password" type="password" id="SFSoc_password" value="<?php echo $this->options['SFSoc_password'] ;?>"/>
                            </td>
                        </tr>
                        <tr valign="top">
                            <th width="33%" scope="row"><?php _e('Verify Password:', SFSoc::LOCALIZATION_DOMAIN); ?></th>
                            <td><input name="SFSoc_vpassword" type="password" id="SFSoc_vpassword" value="<?php echo $this->options['SFSoc_password'] ;?>"/>
                            </td>
                        </tr>
                        <tr valign="top">
                            <th><label for="SFSoc_bp_xprofiles"><?php _e('Read Buddypress Extended User Profiles:', SFSoc::LOCALIZATION_DOMAIN); ?></label></th>
                            <td><input type="checkbox" id="SFSoc_bp_xprofiles" name="SFSoc_bp_xprofiles" <?php echo $chkBPXProfileAdmin ?>></td>
                        </tr>
                        <tr valign="top">
                            <th><label for="SFSoc_https"><?php _e('HTTPS Only:', SFSoc::LOCALIZATION_DOMAIN); ?></label></th>
                            <td><input type="checkbox" id="SFSoc_https" name="SFSoc_https" <?php echo $chkHttps ?>></td>
                        </tr>
                        <tr valign="top">
                            <th width="33%" scope="row"><?php _e('Whitelist (IPs/CIDRs):', SFSoc::LOCALIZATION_DOMAIN); ?></th>
                            <td><input name="SFSoc_whitelist" type="text" id="SFSoc_whitelist" size="75" value="<?php echo $this->options['SFSoc_whitelist'] ;?>"/>
                                <br><?php _e('(Comma separated e.g. 204.14.232.0/25,204.14.231.127,204.14.231.111)', SFSoc::LOCALIZATION_DOMAIN); ?>
                            </td>
                        </tr>
                        <tr valign="top">
                            <th width="33%" scope="row"><?php _e('REST API Usage:', SFSoc::LOCALIZATION_DOMAIN); ?></th>
                            <td><?php _e('transaction=getusers|getuserbyid|updateuser|deleteuser', SFSoc::LOCALIZATION_DOMAIN); ?></br>
                                http://[Wordpress/Buddyppress URL]/sfs_api?transaction=mytransaction&ID=0&user=myusername&password=mypassword
                            </td>
                        </tr>
                        <tr>
                            <th colspan=2><input type="submit" name="SFSoc_save" value="Save" /></th>
                        </tr>
                    </table>
                </form>
                </div>
                <?php
        }

        /*
        * ============================
        * Plugin Widgets
        * ============================
        */
        function register_widgets() {
            //Make sure the widget functions exist
            if ( function_exists('wp_register_sidebar_widget') ) {
                //============================
                //Example Widget 1
                //============================
                function display_SFSocWidget($args) {
                    extract($args);
                    echo $before_widget . $before_title . $this->options['title'] . $after_title;
                    echo '<ul>';
                    //!!! Widget 1 Display Code Goes Here!
                    echo '</ul>';
                    echo $after_widget;
                }
                function SFSocWidget_control() {
                    if ( $_POST["SFSoc_SFSocWidget_submit"] ) {
                        $this->options['SFSoc-comments-title'] = stripslashes($_POST["SFSoc-comments-title"]);
                        $this->options['SFSoc-comments-template'] = stripslashes($_POST["SFSoc-comments-template"]);
                        $this->options['SFSoc-hide-admin-comments'] = ($_POST["SFSoc-hide-admin-comments"]=='on'?'':'1');
                        $this->saveAdminOptions();
                    }
                    $title = htmlspecialchars($options['SFSoc-comments-title'], ENT_QUOTES);
                    $template = htmlspecialchars($options['SFSoc-comments-template'], ENT_QUOTES);
                    $hide_admin_comments = $options['SFSoc-hide-admin-comments'];
                ?>
                    <p><label for="SFSoc-comments-title"><?php _e('Title:', SFSoc::LOCALIZATION_DOMAIN); ?> <input style="width: 250px;" id="SFSoc-comments-title" name="SFSoc-comments-title" type="text" value="<?= $title; ?>" /></label></p>
                    <p><label for="SFSoc-comments-template"><?php _e('Template:', SFSoc::LOCALIZATION_DOMAIN); ?> <input style="width: 250px;" id="SFSoc-comments-template" name="SFSoc-comments-template" type="text" value="<?= $template; ?>" /></label></p>
                    <p><?php _e('The template is made up of HTML and tokens. You can get a list of available tokens at the', SFSoc::LOCALIZATION_DOMAIN); ?> <a href='http://pressography.com/plugins/wp-SFSoc/#tokens-recent' target='_blank'><?php _e('plugin page', SFSoc::LOCALIZATION_DOMAIN); ?></a></p>
                    <p><input id="SFSoc-hide-admin-comments" name="SFSoc-hide-admin-comments" type="checkbox" <?= ($hide_admin_comments=='1')?'':'checked="CHECKED"'; ?> /> <label for="SFSoc-hide-admin-comments"><?php _e('Show Admin Comments', SFSoc::LOCALIZATION_DOMAIN); ?></label></p>
                    <input type="hidden" id="SFSoc_SFSocWidget_submit" name="SFSoc_SFSocWidget_submit" value="1" />
                <?php
                }
                $widget_ops = array('classname' => 'SFSocWidget', 'description' => __( 'Widget Description', SFSoc::LOCALIZATION_DOMAIN ) );
                wp_register_sidebar_widget('SFSoc-SFSocWidget', __('Widget Title', SFSoc::LOCALIZATION_DOMAIN), array($this, 'display_SFSocWidget'), $widget_ops);
                wp_register_widget_control('SFSoc-SFSocWidget', __('Widget Title', SFSoc::LOCALIZATION_DOMAIN), array($this, 'SFSocWidget_control'));

            }
        }


        /*
         * @desc API PROCESSING MAIN FUNCTION
         */
        function sfs_api_listener() {

            global $wp;
            // Note : Only works with numeric permalinks for gets else page not found error
            if ( $req = $wp->request ) {

                $ra = explode( '/', $req );

                // Intercept and process api requests then die
                if ( strtolower($ra[0]) == SFSoc::API_ROOT) {

                    try {

                        $this->resetMemberVars();

                        $request_method = strtolower($_SERVER['REQUEST_METHOD']);
                        $action = '';
                        if (!empty($request_method)) {
                            switch ($request_method) {
                                case 'get' :
                                    $this->dataIn = $_GET;
                                    break;
                                case 'post' :
                                    $this->dataIn = $_POST;
                                    break;
                            }
                            $action = strtolower($this->dataIn['transaction']);
                        }

                        if ($this->transpOk() &&
                                $this->sourceOk() &&
                                $this->userPassOk($this->dataIn['user'], $this->dataIn['password'])
                                && $this->actionOk($action)) {

                            // Remove the password - to prevent it being sent back with the response
                            $this->dataIn['password'] = '';

                            $id = $this->dataIn['ID'];

                            switch ($this->transaction) {
                                case 'getusers' :
                                    $this->getUsers($id);
                                    break;
                                case 'getuserbyid' :
                                    if (empty($id)) {
                                        $this->sendInvalidCall('ID must be supplied.');
                                    } else {
                                        $this->getUserByID($id);
                                    }
                                    break;
                                case 'updateuser' :
                                    if (empty($id)) {
                                        $this->sendInvalidCall('ID must be supplied.');
                                    } else {
                                        $this->updateUser($this->dataIn);
                                    }
                                    break;
                                case 'deleteuser' :
                                    if (empty($id)) {
                                        $this->sendInvalidCall('ID must be supplied.');
                                    } else {
                                        $this->deleteUser($this->dataIn);
                                    }
                                    break;
                                default:

                                    $this->sendInvalidCall(' Unknown transaction requested. ['.implode('|', $this->dataIn).']');
                            }

                        } else {
                            $this->sendAccessDenied(' Full request. ['.implode('|', $this->dataIn).']');
                        }

                    }
                    catch (Exception $e) {
                        $this->sendError('Problem in sfs_api_listener with data ['.implode('|', $this->dataIn).']', $e);
                    }

                    // End API processing
                    die();
                }
            }
        }


        /*
         * General helper functions
         */
        function resetMemberVars() {
            $this->transaction = '';
            $this->statusCode = '';
            $this->statusDescription = '';
            $this->statusMessage = '';
            $this->dataIn = '';
        }

        /*
         * XML message helper functions
         */

        /*
         * @desc sends an array as xml to the caller
         */
        function sendRecordSet($recordSetArr, $pluralTag, $singularTag) {
            $this->statusCode = SFSoc::CODE_SUCCESS;
            $this->statusDescription = 'success';
            $this->statusMessage = 'All Ok';

            try {

                echo XmlHandler::buildDbXml(SFSoc::API_NAME,
                        SFSoc::API_VERSION,
                        $this->transaction,
                        $this->statusCode,
                        $this->statusDescription,
                        $this->statusMessage,
                        $recordSetArr, $pluralTag, $singularTag);
            }
            catch (Exception $e) {
                $this->sendError('Problem in sendRecordSet. ['.implode('|', $this->dataIn).']', $e);
            }

        }


        /*
         * @desc sends an xml message to indicate an invalid call
         */
        function sendInvalidCall($details) {
            $this->statusCode = SFSoc::CODE_FAILURE;
            $this->statusDescription = 'failure';
            if (empty($details)) {
                $this->statusMessage = 'Invalid call';
            } else {
                $this->statusMessage = 'Invalid call : '.$details;
            }

            echo XmlHandler::buildStatusXml(SFSoc::API_NAME,
                    SFSoc::API_VERSION,
                    $this->transaction,
                    $this->statusCode,
                    $this->statusDescription,
                    $this->statusMessage, null);
        }


        /*
         * @desc sends an xml message to indicate the succesful processing of a transaction
         */
        function sendSuccess($details, $contentXml) {
            $this->statusCode = SFSoc::CODE_SUCCESS;
            $this->statusDescription = 'success';
            if (empty($details)) {
                $this->statusMessage = 'success';
            } else {
                $this->statusMessage = $details;
            }

            echo XmlHandler::buildStatusXml(SFSoc::API_NAME,
                    SFSoc::API_VERSION,
                    $this->transaction,
                    $this->statusCode,
                    $this->statusDescription,
                    $this->statusMessage, $contentXml);
        }

        /*
         * @desc sends an xml message to indicate access denied
         */
        function sendAccessDenied($details) {
            $this->statusCode = SFSoc::CODE_FAILURE;
            $this->statusDescription = 'failure';
            if (empty($details)) {
                $this->statusMessage = 'Access Denied';
            } else {
                $this->statusMessage = 'Acess Denied / Restricted or unkonwn transaction: '.$details;
            }
            echo XmlHandler::buildStatusXml(SFSoc::API_NAME,
                    SFSoc::API_VERSION,
                    $this->transaction,
                    $this->statusCode,
                    $this->statusDescription,
                    $this->statusMessage, null);
        }

        /*
         * @desc sends an xml message to indicate and detail a system error
         */
        function sendError($details, $excep) {
            $this->statusCode = SFSoc::CODE_ERROR;
            $this->statusDescription = 'error';
            if (empty($details)) {
                $this->statusMessage = 'Error during call with '.$details.' : '.$excep->getMessage();
            } else {
                $this->statusMessage = $excep->__toString();
            }

            echo XmlHandler::buildStatusXml(SFSoc::API_NAME,
                    SFSoc::API_VERSION,
                    $this->transaction,
                    $this->statusCode,
                    $this->statusDescription,
                    $this->statusMessage, null);
        }

        /*
         * Authentication helper functions
         */

        /*
         * @desc validates requested actions / transactions
         */
        function actionOk($act) {
            $retVal = false;

            if ($this->options['SFSoc_enable_api']) {
                if ($act == 'getuserbyid') {
                    $retVal = true;
                } else if ($act == 'updateuser') {
                    $retVal = true;
                } else if ($act == 'deleteuser') {
                    $retVal = true;
                } else if ($act == 'getusers') {
                    $retVal = true;
                }
            }

            if ($retVal) {
                $this->transaction = $act;
            } else {
                $this->transaction = '';
            }

            return $retVal;
        }

        /*
         * @desc validates transprot method
         */
        function transpOk() {
            $remote_https = $_SERVER ['HTTPS'];
            if ($this->options['SFSoc_https']==true) {
                if (empty($remote_https)) {
                    return false;
                }
            }
            return true;
        }

        /*
         * @desc validates the callling IP address to be in a white list from the options
         */
        function sourceOk() {

            try {

            $wl = trim($this->options['SFSoc_whitelist']) . ",";
            //if the white list is not already terminated with "," then add 1
            $remote_name = $_SERVER ['REMOTE_HOST'];
            $remote_addr = $_SERVER ['REMOTE_ADDR'];
            //$remote_addr = '204.14.232.2'; // testing
            if (!empty($wl)) {
                $wlarr = explode(',', $wl, -1);
                for($i = 0; $i < count($wlarr); $i++){

                    // If IP address
                    if ($remote_addr == $wlarr[$i]) {
                        return true;
                        break;
                    } else {

                        // If CIDR
                        $netstr = strstr($wlarr[$i], '/', true);
                        $subnetstr = strstr($wlarr[$i], '/');
                        if (!empty($netstr) && !empty($subnetstr)) {

                            if(SFSoc::cidr_match($remote_addr,$wlarr[$i])) {
                                return true;
                                break;
                            }

                        }

                    }

                }
                return false;
            }

            return true;
            }
            catch (Exception $e) {
                $this->sendError('Problem in sourceOk. ['.implode('|', $this->dataIn).']', $e);
            }

        }

        /*
         * @desc validates user name and password
         */
        function userPassOk($usr, $pw) {
            try {
                $retVal = false;
                if (!empty($usr) && !empty($pw)) {
                    if ($usr == trim($this->options['SFSoc_user'])) {
                        if(wp_check_password($pw, $this->options['SFSoc_password'])) {
                            $retVal = true;
                        }
                    }
                }
                return $retVal;
            }
            catch (Exception $e) {
                $this->sendError('Problem in userPassOk. ['.implode('|', $this->dataIn).']', $e);
            }
        }

        /*
         * @desc validates source IP against a CIDR format IP address range
         */
        static function cidr_match($ipStr, $cidrStr) {
          $ip = ip2long($ipStr);
          $cidrArr = split('/',$cidrStr);
          $maskIP = ip2long($cidrArr[0]);
          $maskBits = 32 - $cidrArr[1];
          return (($ip>>$maskBits) == ($maskIP>>$maskBits));
        }

        /*
         * USER ADMIN
         */

        function getBpXprofSql() {

                    global $bp;

                    $xProSql=array();
                    $xProSql[]="SELECT grp.id as group_id, grp.`name` as group_name, ";
                    $xProSql[]="fld.id as field_id, fld.`type` as field_type, fld.`name` as field_name, ";
                    $xProSql[]="dat.id as data_id, dat.`value` as data_value, dat.last_updated as data_last_updated ";
                    $xProSql[]="FROM ".$bp->profile->table_name_groups." grp ";
                    $xProSql[]="INNER JOIN ".$bp->profile->table_name_fields." fld ON grp.id = fld.group_id ";
                    $xProSql[]="INNER JOIN ".$bp->profile->table_name_data." dat ON fld.id = dat.field_id ";
                    $xProSql[]="WHERE dat.user_id = ";

                    return implode('',$xProSql);

        }

        function getUserXProfXml($usr) {

            global $wpdb;

            $res=array();
            $res[]="<user>\n";

            foreach($usr as $key2 => $usrField) {
                $res[]="\t<$key2>$usrField</$key2>\n";
            }

            $res[]="\t<xprofile>\n";
            $xprof = $wpdb->get_results($this->getBpXprofSql().$usr[ID], ARRAY_A);
            foreach($xprof as $key3 => $xproPart) {
                $res[]="\t\t<xprofile_field>\n";
                foreach($xproPart as $key4 => $xproField) {
                    $res[]="\t\t\t<$key4>$xproField</$key4>\n";
                }
                $res[]="\t\t</xprofile_field>\n";
            }
            $res[]="\t</xprofile>\n";
            $res[]="</user>\n";

            return implode('',$res);
        }

        /*
         * @desc returns a user list for IDs > than the ID (if) provided except for the primary admin user
         */
	function getUsers($continueFromId) {
            try {

                global $wpdb;
                $users = null;

                if (empty($continueFromId)) {
                    $users = $wpdb->get_results("SELECT * FROM ".$wpdb->users." WHERE ID > 0 ORDER BY ID LIMIT ".SFSoc::MAX_ROWS, ARRAY_A);
                } else {
                    $users = $wpdb->get_results("SELECT * FROM ".$wpdb->users." WHERE ID > ".$continueFromId." ORDER BY ID LIMIT ".SFSoc::MAX_ROWS, ARRAY_A);
                }

                if (!$this->options['SFSoc_bp_xprofiles']) {
                    $this->sendRecordSet($users, 'users', 'user');
                } else {

                    $resArr=array();
                    $resArr[]='<users>';
                    foreach ($users as $key1 => $usr) {
                        $resArr[]=$this->getUserXProfXml($usr);
                    }
                    $resArr[]='</users>';

                    $this->sendSuccess('users inc. bp extended profile data. ['.implode('|', $this->dataIn).'] : '.$msg, implode('',$resArr));

                }

            }
            catch (Exception $e) {
                $this->sendError('Problem in getUsers. ['.implode('|', $this->dataIn).']', $e);
            }

	}

        /*
         * @desc returns user details
         */
	function getUserByID($ID) {
            try {
                global $wpdb;
                $users = $wpdb->get_results("SELECT * FROM ".$wpdb->users." WHERE ID = ".$ID, ARRAY_A);
                if (!$this->options['SFSoc_bp_xprofiles']) {
                    $this->sendRecordSet($users, 'users', 'user');
                } else {
                    $resArr=array();
                    $resArr[]='<users>';
                    foreach ($users as $key1 => $usr) {
                        $resArr[]=$this->getUserXProfXml($usr);
                    }
                    $resArr[]='</users>';
                    $this->sendSuccess('user inc. bp extended profile data. ['.implode('|', $this->dataIn).'] : '.$msg, implode('',$resArr));
                }

            }
            catch (Exception $e) {
                $this->sendError('Problem in getUserById. ['.implode('|', $this->dataIn).']', $e);
            }
	}

        /*
         * @desc insert or update a user
         */
	function updateUser($userdata) {

            try {

                require_once(ABSPATH . '/wp-includes/registration.php');

                // Attempt update / insert
                $user_id = wp_insert_user($userdata);

                // Respond with the updated data or error details
                if (is_object($user_id)) {
                    $this->sendInvalidCall($user_id->get_error_message());
                } else {
                    $this->getUserByID($user_id);
                }
            }
            catch (Exception $e) {
                $this->sendError('Problem in updateUser. ['.implode('|', $this->dataIn).']', $e);
            }

        }

        /*
         * @desc delete a user
         */
        function deleteUser($params) {
            // http://localhost/wp2/sfs_api?user=Pete&password=123456789&transaction=deleteUser&ID=16

            global $wpdb;

            try
            {
                $retVal = false;
                $msg = '';

                require_once(ABSPATH . '/wp-admin/includes/user.php');
                extract($params, EXTR_SKIP);
                if ( !empty($ID) ) {
                        $ID = (int) $ID;
                        $users = $wpdb->get_results("SELECT ID FROM ".$wpdb->users." WHERE ID = ".$ID);
                        if(count($users) == 1) {
                            if ( !empty($reassign_user) ) {
                                $reassign_user = (int) $reassign_user;
                                $users = $wpdb->get_results("SELECT ID FROM ".$wpdb->users." WHERE ID = ".$reassign_user);
                                if(count($users) == 1) {
                                    $msg = _e('Delete user with ID: ', SFSoc::LOCALIZATION_DOMAIN).$ID._e(' reasigning content to user with ID ', SFSoc::LOCALIZATION_DOMAIN).$reassign_user;
                                    if (defined('BP_VERSION')) {
                                        xprofile_remove_data( $ID );
                                    }
                                    wp_delete_user($ID, $reassign_user);
                                    $retVal = true;
                                }
                                else {
                                    $msg = _e('No user with ID/reassign_user: ', SFSoc::LOCALIZATION_DOMAIN).$reassign_user._e(' to reassign to for user with ID: ', SFSoc::LOCALIZATION_DOMAIN).$ID._e('! (Nothing deleted).', SFSoc::LOCALIZATION_DOMAIN);
                                }

                            } else {
                                $msg = _e('Deleted user with ID: ', SFSoc::LOCALIZATION_DOMAIN).$ID;
                                if (defined('BP_VERSION')) {
                                    xprofile_remove_data( $ID );
                                }
                                wp_delete_user($ID);
                                $retVal = true;
                            }
                        }
                        else {
                            $msg = _e('No user with ID: ', SFSoc::LOCALIZATION_DOMAIN).$ID._e(' to delete!', SFSoc::LOCALIZATION_DOMAIN);
                        }

                } else {
                    $msg = _e('ID must be supplied!', SFSoc::LOCALIZATION_DOMAIN);
                }

                if (retVal) {
                    $this->sendSuccess('Ok. ['.implode('|', $this->dataIn).'] : '.$msg, null);
                }
                else {
                    $this->sendInvalidCall('Problem. ['.implode('|', $this->dataIn).'] : '.$msg);
                }
            }
            catch (Exception $e) {
                $this->sendError('Problem in deleteUser. ['.implode('|', $this->dataIn).']', $e);
            }

        }

        function getWebToLeadForm() {

            $numero1 = rand(100, 999);
            $numero2 = rand(1, 5);
            $operation = rand(1, 2);

            $key = get_option(SFSoc::WEB_TO_LEAD_CAPTCHA_KEY_OPTIONS_NAME);
            $encNumero1 = SFSoc::encodeNumber($numero1, $key);
            $encNumero2 = SFSoc::encodeNumber($numero2, $key);
            $encOperation = SFSoc::encodeNumber($operation, $key);

            $ans = $numero1-$numero2;
            if ($operation > 1) {
                $ans = $numero1+$numero2;
            }

            $dat = $encNumero1.$encOperation.$encNumero2;

            // Get captcha appearance details
            /*
                    ''=>'230',
                    'SFSoc_w2l_ctch'=>'240',
                    'h'=>'',
                    ''=>''

*/
            $captchaAppearance = '&bgcol='.$this->options['SFSoc_w2l_cbgc'].'&bglocol='.$this->options['SFSoc_w2l_cbgcl'].'&bghicol='.$this->options['SFSoc_w2l_cbgch'].'&txtlocol='.$this->options['SFSoc_w2l_ctcl'].'&txthicol='.$this->options['SFSoc_w2l_ctch'].'&ba='.$this->options['SFSoc_w2l_cba'].'&rl='.$this->options['SFSoc_w2l_carl'];


            $hashAns=md5($ans);
            $frmData = get_option(SFSoc::WEB_TO_LEAD_FORM_OPTIONS_NAME);
            $frmData = html_entity_decode($frmData);
            $frmData = str_ireplace('#sfscapthas#', $this->thispluginurl.'captchas.php?dat='.$dat.$captchaAppearance, $frmData);
            $frmData = str_ireplace('#hashAns#', $hashAns, $frmData);
            return $frmData;
        }

        static function encodeNumber($inNum, $key) {
            $inNumStr = strval($inNum);
            $numEnc = '';
            if (!empty($key)) {
                for ($i = 0; $i < strlen($inNumStr); $i++) {
                    $dig = $inNumStr[$i];
                    $ch = $key[strval($dig)];
                    $numEnc = $numEnc.$ch;
                }
            }

            return $numEnc;
        }

        static function decodeToNumber($inStr, $key) {
            $retVal = 0;
            $strNum = '';
            if (!empty($key)) {
                for ($i = 0; $i < strlen($inStr); $i++) {
                    $keyChar = $inStr[$i];
                    $pos = strpos($key, $keyChar);
                    $strNum = $strNum.$pos;
                }
                $retVal = strval($strNum);
            }
            return $retVal;
        }

        static function getNewEncodeKey() {
            $initKey = md5(rand(100000, 999999)).md5(rand(100000, 999999)).md5(rand(100000, 999999)).md5(rand(100000, 999999));
            $finalKey = '';

            for ($i = 0; $i < strlen($initKey); $i++) {
                $partKey = $initKey[$i];
                $pos = strpos($finalKey, $partKey);
                if ($pos === false) {
                    $finalKey = $finalKey.$partKey;
                    if (strlen($finalKey) > 9) {
                        break;
                    }
                }
            }

            return $finalKey;
        }

        function webToLead() {
            if (!isset($_POST['submit'])) {
                return $this->getWebToLeadForm();
            } else {
                $msg = $this->validWebToLeadForm();
                if ('OK' == $msg) {
                    return $this->sendToSalesForce();
                } else {
                    return $msg;
                }
            }
        }

        function sendToSalesForce() {

            try {

                $formExtras = array('oid' => $this->options['SFSoc_w2l_org'], 'lead_source' => $this->options['SFSoc_w2ls']);
                $formVals = array_merge($_POST, $formExtras);
                $hd = array('user-agent' => 'Salesforce Social - '.get_bloginfo('url'));
                $msg = array('body' => $formVals, 'headers' => $hd, 'sslverify' => false);
                $sfResponse = wp_remote_post($this->options['SFSoc_w2l_action'], $msg);
                if (is_wp_error($sfResponse)) {
                    $respMsg = __('Transmission problem!', SFSoc::LOCALIZATION_DOMAIN);
                    $code = 0;
                } else {
                    $respMsg=$sfResponse['response']['message'];
                    $code=$sfResponse['response']['code'];
                }
                if (200 == $code) {
                    $succMsg = $this->options['SFSoc_w2l_success'];
                    if (empty($succMsg)) {
                        return 'OK';
                    } else {
                        return $succMsg;
                    }
                } else  {
                    $failMsg = $this->options['SFSoc_w2l_failure'];
                    if (empty($failMsg)) {
                        return __('There were problems sending your submission. Please go back and try again.',SFSoc::LOCALIZATION_DOMAIN).' - '.$respMsg;
                    } else {
                        return $failMsg.' - '.$respMsg;
                    }
                }
            }
            catch (Exception $e) {
                echo '<div><p>ERROR: '.$e->getMessage().'</p></div>'; 
            }

        }


        function validWebToLeadForm() {
            $retVal = 'BAD';
            $hashans = $_POST['hashans'];
            $ans = $_POST['ans'];
            if (empty($hashans)) {
                $retVal = 'OK';
            }
            else if (empty($ans)) {
                $retVal = __('Please go back and enter an answer to the sum.', SFSoc::LOCALIZATION_DOMAIN);
            }
            else {

                if (md5($ans)==$hashans) {
                    $retVal = 'OK';
                } else {
                    $retVal = __('Wrong answer to the sum. Please go back and try again.', SFSoc::LOCALIZATION_DOMAIN);
                }

            }

            return $retVal;
        }

        function sanitizeWebToLeadForm($addCaptcha, $addCss) {
            if (!empty($this->webToLeadForm)) {

                $origForm = stripslashes($this->webToLeadForm);
                $this->webToLeadForm = $origForm;

                $addValidation = false;
                $cnt = count($this->webToLeadMandInputFields);
                if ($cnt > 0) {
                    $addValidation = true;
                }


                $this->webToLeadForm = preg_replace('/<!--(.|\s)*?-->/', '', $this->webToLeadForm);
                $this->webToLeadForm = preg_replace("/(^[\r\n]*|[\r\n]+)[\s\t]*[\r\n\']+/", "\n", $this->webToLeadForm);
                $this->webToLeadForm = preg_replace('/<META HTTP-EQUIV(.|\s)*?>/i', '', $this->webToLeadForm);

                $this->webToLeadForm = preg_replace('/<input type=hidden name="oid"(.|\s)*?>/i', '', $this->webToLeadForm);

                if (addValidation) {
		            $this->webToLeadForm = preg_replace('/<form action(.|\s)*?>/i', '<div id="w2lerrors"></div><form method="POST" onsubmit="return valw2l()">', $this->webToLeadForm);
                } else {
	                $this->webToLeadForm = preg_replace('/<form action(.|\s)*?>/i', '<form method="POST">', $this->webToLeadForm);
                }

                $this->webToLeadForm = preg_replace('/<input type="submit"(.|\s)*?>/i', '', $this->webToLeadForm);
                $this->webToLeadForm = preg_replace('/<input type=hidden name="retURL"(.|\s)*?>/i', '', $this->webToLeadForm);

/*
                $this->webToLeadForm = preg_replace('/<form action(.|\s)*?>/i', '', $this->webToLeadForm);
                $this->webToLeadForm = str_ireplace('<textarea', '<n_text_n', str_ireplace('</textarea>', '</n_text_n>', $this->webToLeadForm));
                $this->webToLeadForm = preg_replace("/(^[\r\n]*|[\r\n]+)[\s\t]*[\r\n\']+/", "\n", $this->webToLeadForm);
                $this->webToLeadForm = str_ireplace('</form>', '', $this->webToLeadForm);
*/

                if ($addCss) {

                    $style=array();
                    $style[]="<style type=text/css>\n";
                    $style[]="  label.sfs {clear:left;margin:6px 0;width:45%;}\n";
                    $style[]="  input.sfs {width:45%;height:20px;margin:4px 0;}\n";
                    $style[]="  input.sfscaptcha {width:5%;height:18px;margin:4px 0;}\n";
                    $style[]="  textarea.sfs {clear:both;width:80%;height:75px;margin:10px 0;}\n";
                    $style[]="</style>\n";

                    if ($addValidation) {
                        $this->webToLeadForm = str_ireplace('<label for="', '<label class="sfs" id="label_', $this->webToLeadForm);
                    } else {
                        $this->webToLeadForm = str_ireplace('<label ', '<label class="sfs" ', $this->webToLeadForm);
                    }

                    $this->webToLeadForm = str_ireplace('<input ', '<br><input class="sfs" ', $this->webToLeadForm);
                    $this->webToLeadForm = str_ireplace('<textarea ', '<br><textarea class="sfs" ', $this->webToLeadForm);


                    $this->webToLeadForm = implode('',$style).$this->webToLeadForm;
                }

                if ($addCaptcha) {
                    $captchLabelText = __('As an added security measure, please enter the answer to the sum you see in the box above.', SFSoc::LOCALIZATION_DOMAIN);
                    $captchaLabel = '';
                    if ($addCss) {
                        $captchaLabel = '<label class="sfs" id="label_ans">'.$captchLabelText.'</label><br>';
                        $this->webToLeadForm = str_ireplace('</form>', '<br><img src="#sfscapthas#"><br>'.$captchaLabel.'<input class="sfscaptcha" id="ans" maxlength="10" name="ans" size="10" type="text"/><input type="hidden" name="hashans" value="#hashAns#"><br><br><input type="submit" name="submit" value="Submit"></form>', $this->webToLeadForm);
                    } else {
                        $captchaLabel = '<label id="label_ans">'.$captchLabelText.'</label><br>';
                        $this->webToLeadForm = str_ireplace('</form>', '<br><img src="#sfscapthas#"><br>'.$captchaLabel.'<input id="ans" maxlength="10" name="ans" size="10" type="text"/><input type="hidden" name="hashans" value="#hashAns"<br><input type="submit" name="submit" value="Submit"></form>', $this->webToLeadForm);
                    }
                } else {
                    $this->webToLeadForm = str_ireplace('</form>', '<br><input type="submit" name="submit" value="Submit"></form>', $this->webToLeadForm);
                }


                if ($addValidation) {
                    //http://www.csgnetwork.com/directcssformvalidate.html
                    $valScript=array();
                    $valScript[]="<style type=text/css>\n";
                    $valScript[]=".w2lerror {\n";
                    $valScript[]=" 	color: #FF0000;\n";
                    $valScript[]="}\n";
                    $valScript[]="span {\n";
                    $valScript[]=" 	font-weight: bold;\n";
                    $valScript[]="}\n";
                    $valScript[]="</style>\n\n";
                    $valScript[]="\n<SCRIPT language=JavaScript>\n";
                    $valScript[]="function valw2l() {\n";
                    $valScript[]="\tif(!document.getElementById) return;\n";
					$valScript[] = "\t    var errCnt = 0;\n";

                    for ($i = 0; $i < $cnt; $i++) {
                        $fieldName=$this->webToLeadMandInputFields[$i];
			$valScript[] = "\t    errCnt = errCnt + fldVal(document.getElementById('".$fieldName."'), document.getElementById('label_".$fieldName."'));\n";
                    }
                    if ($addCaptcha) {
                        $valScript[] = "\t    errCnt = errCnt + fldVal(document.getElementById('ans'), document.getElementById('label_ans'));\n";
                    }
                    


                    $valScript[] = "\t    if(errCnt > 0) {\n";
                    $valScript[] = "\t        document.getElementById('w2lerrors').innerHTML = \"<span class='w2lerror'>".__('Please complete or correct the fields labelled in RED.' ,SFSoc::LOCALIZATION_DOMAIN)."</span><br/>\";\n";
                    $valScript[] = "\t        return false;\n";
                    $valScript[] = "\t    } else {\n";
                    $valScript[] = "\t        document.getElementById('w2lerrors').innerHTML = \"\";\n";
                    $valScript[] = "\t        return true;\n";
                    $valScript[] = "\t    }\n";
                    $valScript[] = "\t}\n";
                    $valScript[] = "\t\n";
                    $valScript[] = "\tfunction fldVal(ipField, ipLabel) {\n";
                    $valScript[] = "\t    var erCnt = 0;\n";
                    $valScript[] = "\t    if (ipField != null && ipLabel != null) {\n";
                    $valScript[] = "\t        if (ipEmpty(ipField, ipLabel) == true) {\n";
                    $valScript[] = "\t            erCnt++;\n";
                    $valScript[] = "\t        } else if (!emailAddrIsValid(ipField, ipLabel)){\n";
                    $valScript[] = "\t            erCnt++;\n";
                    $valScript[] = "\t        }\n";
                    $valScript[] = "\t    }\n";
                    $valScript[] = "\t    return erCnt;\n";
                    $valScript[] = "\t}\n";
                    $valScript[] = "\t\n";
                    $valScript[] = "\tfunction ipEmpty(ip, ipLab) {\n";
                    $valScript[] = "\t    if (ip == null) {\n";
                    $valScript[] = "\t        return true;\n";
                    $valScript[] = "\t    } else {\n";
                    $valScript[] = "\t        if (ip.value == null || ip.value == \"\") {\n";
                    $valScript[] = "\t            ipLab.style.color=\"#FF0000\"\n";
                    $valScript[] = "\t            return true;\n";
                    $valScript[] = "\t        } else {\n";
                    $valScript[] = "\t            ipLab.style.color=\"#000000\"\n";
                    $valScript[] = "\t            return false;\n";
                    $valScript[] = "\t        }\n";
                    $valScript[] = "\t    }\n";
                    $valScript[] = "\t}\n";
                    $valScript[] = "\t\n";
                    $valScript[] = "\tfunction emailAddrIsValid(ip, ipLab) {\n";
                    $valScript[] = "\t    if (ip.id == 'email') {\n";
                    $valScript[] = "\t        var reg = /^([A-Za-z0-9_\-\.])+\@([A-Za-z0-9_\-\.])+\.([A-Za-z]{2,4})$/;\n";
                    $valScript[] = "\t        addr = ip.value;\n";
                    $valScript[] = "\t        if(reg.test(addr)) {\n";
                    $valScript[] = "\t            ipLab.style.color=\"#000000\"\n";
                    $valScript[] = "\t            return true;\n";
                    $valScript[] = "\t        } else {\n";
                    $valScript[] = "\t            ipLab.style.color=\"#FF0000\"\n";
                    $valScript[] = "\t            return false;\n";
                    $valScript[] = "\t        }\n";
                    $valScript[] = "\t    } else {\n";
                    $valScript[] = "\t        return true;\n";
                    $valScript[] = "\t    }\n";
                    $valScript[] = "\t}\n";
                    $valScript[]="</script>\n";


                    $this->webToLeadForm = str_ireplace('</form>', implode('',$valScript).'</form>', $this->webToLeadForm);
                }

                //$this->webToLeadForm = htmlentities($this->webToLeadForm);

            }
        }









        function analyseWebToLeadForm() {

            try {

                if (!empty($this->webToLeadForm)) {

                    /*
                     * Attempt to get the submission url and the org id is not already entered
                     */
                    if (empty($this->webToLeadForm_Action) || empty($this->webToLeadForm_OID)) {
                        $doc = new DOMDocument();

    //                    $html = stripslashes($this->webToLeadForm);
    //                    $html = str_ireplace('<n_text_n', '<textarea', str_ireplace('</n_text_n>', '</textarea>', $html));
                        $doc->loadHTML($this->webToLeadForm);
                        $frm = $doc->getElementsByTagName('form')->item(0);
                        if (!empty($frm)) {
                            $this->webToLeadForm_Action = $frm->getAttribute('action');
                            $elems = $frm->getElementsByTagName('input');

                            $this->webToLeadInputFields = null;

                            foreach ($elems as $elem ) {

                                $ty = $elem->getAttribute('type');
                                $nm = $elem->getAttribute('name');

                                if ('oid' == $nm && 'hidden' == $ty) {
                                    $this->webToLeadForm_OID = $elem->getAttribute('value');
                                } else if('hidden' != $ty && 'submit' != $ty) {
                                    $this->webToLeadInputFields[] = $nm;
                                }

                            }



                        }
                    }

                }

            }
            catch (Exception $e) {
                echo '<div><p>'._e('Could not analyse form. Please enter a new / fresh form generated by Salesforce.com!', SFSoc::LOCALIZATION_DOMAIN).'</p></div>';
            }

        }

        function IsSanitized() {
            if (empty($this->webToLeadForm)) {
                return false;
            } else {
                if (stripos(html_entity_decode($this->webToLeadForm),'<form action="https')) {
                    return false;
                } else {
                    return true;
                }
            }
        }

    static function getOptionsHtml($array, $active, $echo=true) {

        $string = '';

        foreach($array as $k => $v){
            if(is_array($active))
                $s = (in_array($k, $active))? ' selected="selected"' : '';
            else
                $s = ($active == $k)? ' selected="selected"' : '';
//            $string .= '<option value="'.$k.'"'.$s.'>'.$v.'</option>'."\n";
            $string .= '<option value="'.$v.'"'.$s.'>'.$v.'</option>'."\n";
        }

        if($echo)   echo $string;
        else        return $string;
    }



  } //End Class
} //End if class exists statement

//instantiate the class
if (class_exists('SFSoc')) {
    $SFSoc_var = new SFSoc();
}
?>