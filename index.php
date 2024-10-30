<?php
/*
Plugin Name: Cam Site Builder
Plugin URI: https://www.modelnet.club/wordpress
Description: With <a href="https://www.modelnet.club/wordpress" target="_blank">Cam Site Builder</a> plugin you can easily build a webcam site section on your Wordpress site. 
Version: 1.0.1
Author: www.modelnet.club
Author URI: https://www.modelnet.club
*/


define( 'CAMSITEBUILDER_PLUGIN_NAME', 'camsitebuilder' ); // don't change it whatever

// this is the function that outputs the background as a style tag in the <head>
if ( ! defined( 'WP_CONTENT_URL' ) )
      define( 'WP_CONTENT_URL', get_option( 'siteurl' ) . '/wp-content' );
if ( ! defined( 'WP_CONTENT_DIR' ) )
      define( 'WP_CONTENT_DIR', ABSPATH . 'wp-content' );
if ( ! defined( 'WP_PLUGIN_URL' ) )
      define( 'WP_PLUGIN_URL', WP_CONTENT_URL. '/plugins' );
if ( ! defined( 'WP_PLUGIN_DIR' ) )
      define( 'WP_PLUGIN_DIR', WP_CONTENT_DIR . '/plugins' );


class CamSiteBuilderPlugin {
  static protected $version = 1;
  static public $gatewayUri = '/widgets/modelnetclub-camsitebuilder/api.jsp';

  static public function do_ajaj_request($url, $data_ar)
  {
    $response = file_get_contents($url . '?' . http_build_query($data_ar));
    return json_decode($response, true);
  }

  static public function salt($data_ar, $secret_key)
  {
    $data_ar['salt'] = uniqid();
    ksort($data_ar, SORT_STRING);
    $data_ar['hash'] = hash('sha256', implode('', $data_ar) . $secret_key);
    return $data_ar;
  }



  static public function control_config_page()
  {
    if ( function_exists('add_submenu_page') ) {
      add_submenu_page('options-general.php', __('Cam Site Builder Configuration'), __('Cam Site Builder'), 'manage_options', 'camsitebuilder-control', array('CamSiteBuilderPlugin', 'control_conf'));
    }
  }

  /**
  * This is the function that outputs our configuration page
  */
  static public function control_conf() {
    $settings = get_option('camsitebuilder_settings');
    $command = isset($_POST['camsitebuilder-control-cmd']) ? $_POST['camsitebuilder-control-cmd'] : '';

    if ($command == 'login') {
      $resp = CamSiteBuilderPlugin::do_ajaj_request($settings['gateway_url'], array('cmd'=>'webmaster-login', 'name'=>$_POST['name'], 'passw'=>$_POST['passw']));
      if ($resp['result'] !== false)
      {
        $settings['webmaster_id'] = $resp['id'];
        $settings['webmaster_name'] = $resp['name'];
        $settings['secret_key'] = $resp['k'];
        $settings['activated'] = 'activated';
        update_option('camsitebuilder_settings', $settings);
      }
      else
      {
        $error_msg = "Wrong password, please try again.";
      }
    }
    else if ($command == 'update') {
      $settings['update_interval'] = intval($_POST['update_interval']);
      update_option('camsitebuilder_settings', $settings);
    }
//    var_dump($settings);

    $is_activated = $settings['activated'] == 'activated';
    echo <<<HTML
    <div class="wrap">

    <h2>Cam Site Builder</h2>
    <div class="notice notice-info">
    <p>
    IMPORTANT! This plugin doesn't provide you ability to add your own models and take money from customers.
    <br />
    To build individual webcam site please visit <a target="_blank" href="https://www.modelnet.club">https://www.modelnet.club</a>.
    </p>
    </div>
HTML;

    print('<div id="camsitebuilder-result">');
    if (!empty($error_msg)) {
      print('<div class="notice notice-error"><p>' . $error_msg . '</p></div>');
    } else if ($is_activated) {
      print(
        '<div class="notice notice-success"><p>' .
        __('The WebVideo plugin is configured successfully.') .
        '<br />' .
        sprintf(__('To add video chat into any page/post of your website just insert following shortcut %s'), '<br /><br /><b>[camsitebuilder webmaster-id="'.$settings['webmaster_id'].'"]</b><br /><br />') .
        sprintf(__('To use a different Modelnet.club webmaster account with your plugin, please click %shere%s'), '<a href="#" onclick="CamSiteBuilderPlugin.showRegistrationForm(); return false;">', '</a>') .
        '</p></div>');
    }
    print('</div>');

    $ref_url = get_home_url();
    $plugin_version = CamSiteBuilderPlugin::$version;
    echo <<<JS
      <script type="text/javascript">
      function CamSiteBuilderPlugin() {}

      CamSiteBuilderPlugin.onSubmitRegistrationForm = function(form)
      {
        var formResultContainer = jQuery('#camsitebuilder-result-join').html(""),
            fd = jQuery(form).serializeArray();

        fd.push({"name": "cmd", "value": "webmaster-join"});
        fd.push({"name": "ref_url", "value": "{$ref_url}"});
        fd.push({"name": "provider", "value": "wordpress"});
        fd.push({"name": "plugin", "value": "CamSiteBuilder"});
        fd.push({"name": "plugin_version", "value": "{$plugin_version}"});
        jQuery.ajax({
          "dataType": "jsonp",
          "type": "POST",
          "url": "{$settings['gateway_url']}",
          "data": fd,
          "success": function(resp) {
            if (resp.result) {
              //login
              formResultContainer.html('<div class="notice notice-success"><p>' + resp.msg + '</p></div>');
              var formLogin = document.forms["frm-camsitebuilder-login"];
              formLogin.elements["name"].value = form.elements["name"].value;
              formLogin.elements["passw"].value = form.elements["passw"].value;
              formLogin.submit();
            } else {
              //error
              formResultContainer.html('<div class="notice notice-error"><p>' + resp.msg + '</p></div>');
            }
          },
          "error": function() {
            formResultContainer.html('<div class="error"><p>Connection timeout. Please try later.</p></div>');
          }
        });
        return false;

      };

      CamSiteBuilderPlugin.showRegistrationForm = function()
      {
        jQuery('#camsitebuilder-settings').hide();
        jQuery('#camsitebuilder-login').show();
      };

      </script>
JS;
      print(
        '<div id="camsitebuilder-login"' .
        ($is_activated ? ' style="display:none;"' : '') .
        '>'
      );
      echo <<<HTML
         <span><b>Please log-in with you affiliate account from Modelnet.club to complete the setup, or sign-up below.</b></span>
         <br />

          <form name="frm-camsitebuilder-login" method="post">
            <input type="hidden" name="camsitebuilder-control-cmd" value="login" />
            <table class="form-table">
              <tr>
              <th>Username</th>
              <td>
                <input type="text" name="name" />
              </td>
              </tr>
              <tr>
              <th>Password</th>
              <td>
                <input type="password" name="passw" />
              </td>
              </tr>
            </table>
            <input type="submit" class="button-primary" value="Log-in" />
          </form>
          <br />
          <p>
            <b>New user? Please sign-up for a Modelnet.club affiliate account:</b><br />
            <b>* required fields.</b>
          </p>
          <div id="camsitebuilder-result-join"></div>
          <form method="post" onsubmit="return CamSiteBuilderPlugin.onSubmitRegistrationForm(this);">
            <table class="form-table">
              <tr>
              <th>Username *</th>
              <td>
                <input type="text" name="name" />
              </td>
              </tr>
              <tr>
              <th>Email *</th>
              <td>
                <input type="text" name="email" />
              </td>
              </tr>
              <tr>
              <th>Password *</th>
              <td>
                <input type="password" name="passw" />
              </td>
              </tr>
            </table>
            <input type="submit" class="button-primary" value="Sign-up" />
          </form>
          <br /><br />
          <p>For any assistance please contact <a href="mailto:support@modelnet.club">support@modelnet.club</a></p>
HTML;
    print(
    '</div>' //camsitebuilder-login
    );
    print(
      '<div id="camsitebuilder-settings"' .
      (!$is_activated ? ' style="display:none;"' : '') .
      '>'
    );
    echo <<<HTML
      <form method="post">
        <input type="hidden" name="camsitebuilder-control-cmd" value="update" />
        <table class="form-table">
        <tr>
          <th>Update cams every</th>
          <td><input type="text" name="update_interval" value="{$settings['update_interval']}" placeholder="60" /> sec</td>
        </tr>
        </table>
        <input type="submit" class="button-primary" value="Save" />
      </form>
HTML;
    print(
    '</div>' //camsitebuilder-settings
    );

    print(
      '</div>' //wrap
    );
  }

  static public function init()
  {
    global $pagenow;
    add_action('admin_menu', array('CamSiteBuilderPlugin', 'control_config_page'));
    add_filter('plugin_action_links_' . plugin_basename(__FILE__), array('CamSiteBuilderPlugin', 'add_action_links'));
    $settings = get_option('camsitebuilder_settings');
    if ($settings['activated'] == 'activated') {
      add_filter('plugin_row_meta', array('CamSiteBuilderPlugin', 'add_meta_links'), 10, 2);
      add_shortcode('camsitebuilder', array('CamSiteBuilderPlugin', 'shortcode'));
    } else {
      if ($pagenow  == 'plugins.php') add_action('admin_notices', array('CamSiteBuilderPlugin', 'activation_notice'));
    }
  }

  static public function install()
  {
    $settings = array(
      'server_url' => 'https://www.modelnet.club',
      'gateway_url' => 'https://www.modelnet.club' . CamSiteBuilderPlugin::$gatewayUri
    );
    update_option('camsitebuilder_settings', $settings);
  }

  static public function add_action_links($links)
  {
    $settings_link = '<a href="options-general.php?page=camsitebuilder-control">' . __('Settings') . '</a>';
    array_unshift($links, $settings_link);
    return $links;
  }

  static public function add_meta_links($links, $file) {
    if ($file == plugin_basename(__FILE__)) {
      $settings = get_option('camsitebuilder_settings');
      $webmaster_login_url = $settings['server_url'] . CamSiteBuilderPlugin::$gatewayUri . '?' . http_build_query(CamSiteBuilderPlugin::salt(array(
        'cmd' => 'sso',
        'wm'=> $settings['webmaster_id'],
        'url' => $settings['server_url'] . '/webmaster/welcome',
        'time' => round((microtime(true)+24*3600)*1000) //+1day in milliseconds
        ), $settings['secret_key'])
      );
      $links[] = '<a href="' . $webmaster_login_url . '" target="_blank">' . __('Login as webmaster') . '</a>';
    }
    return $links;
  }

  static public function activation_notice()
  {
    print(
      '<div class="notice notice-warning">' .
      '<p>' .
      '<b>' .
      sprintf(__('Your Cam Site Builder plugin installation is incomplete. Please complete it on the %ssettings page%s.'), '<a href="' . admin_url('options-general.php?page=camsitebuilder-control') . '">', '</a>') .
      '</b>' .
      '</p>' .
      '</div>'
    );
  }

  static public function shortcode($atts = [], $content = null)
  {
    $settings = get_option('camsitebuilder_settings');
    $data_ar = array(
      'cmd'=>'show-models',
      'provider' => 'wordpress',
      'plugin' => "CamSiteBuilder",
      'plugin_version' => CamSiteBuilderPlugin::$version,
      'wm' => empty($atts['webmaster-id']) ? $settings['webmaster_id'] : $atts['webmaster-id'],
      'update_interval' => $settings['update_interval']
  );

    $url = str_replace(array('http:', 'https:'), '', $settings['server_url']) . CamSiteBuilderPlugin::$gatewayUri . '?' . http_build_query($data_ar);
    return '<script type="text/javascript" src="'.$url.'"></script>';
  }

}

register_activation_hook(__FILE__, array('CamSiteBuilderPlugin', 'install'));
add_action('init', array('CamSiteBuilderPlugin', 'init'));

?>
