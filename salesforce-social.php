<?php
/*
Plugin Name: Salesforce Social
Plugin URI: http://www.cornersoftware.co.uk/?page_id=93
Description: Salesforce to Wordpress / Buddypress Integration Suite
Author: Pete Ryan - Corner Software Ltd
Version: 1.0.5
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

    class SFSoc {

        const OPTIONS_NAME = 'SFSoc_options'; // The options string name for this plugin
        const WEB_TO_LEAD_CAPTCHA_KEY_OPTIONS_NAME = 'SFSoc_w2le'; // The options string name for this plugin
        const WEB_TO_LEAD_FORM_OPTIONS_NAME = 'SFSoc_w2lf'; // The options string name for this plugin
        const LOCALIZATION_DOMAIN = 'SFSoc'; // Domain used for localization

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

            //Filters
            /*
            add_filter('the_content', array(&$this, 'filter_content'), 0);
            */

            /*
             * API Interceptor
             */
            // Ensure that the request is parsed before any WP front-end stuff, but after the core of WP is loaded

            add_shortcode('webtolead', array(&$this, 'webToLead'));

        }

        /**
        * Retrieves the plugin options from the database.
        */


        function getOptions() {
            if (!$theOptions = get_option(SFSoc::OPTIONS_NAME)) {
                $theOptions = array(
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


                // If mandatory fields have been selected then CSS is required.
                $this->webToLeadMandInputFields = $_POST['webToLeadMandInputFields'];
                if ($_POST['SFSoc_sanitize'] && count($this->webToLeadMandInputFields) > 0 && !$_POST['SFSoc_w2l_css']=='on') {
                    die(__('CSS must be selected for validation to work. Please go back and try again. (Edit the default CSS included by the sanitization process to meet your needs.)', SFSoc::LOCALIZATION_DOMAIN));
                }


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
                <h4>Wordpress / Buddypress REST API. (Contact www.cornersoftware.co.uk)</h4>
                    <p><?php _e('This feature allows the integration of Salesforce.com/Force.com with Wordpress/Multisite/Buddypress.', SFSoc::LOCALIZATION_DOMAIN); ?>
                    <p><?php _e('It is no longer part of this plugin but is available separately.', SFSoc::LOCALIZATION_DOMAIN); ?>
                    <p><?php _e('For more information visit www.cornersoftware.co.uk', SFSoc::LOCALIZATION_DOMAIN); ?>
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