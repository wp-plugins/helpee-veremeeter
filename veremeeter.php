<?php
/*
Plugin Name: Help.ee Veremeeter
Plugin URI: http://www.help.ee/veremeeter
Description: Help.ee Veremeeter on WP vidin, mis n�itab www.verekeskus.ee vereseisu. Kui olukord on kehv, l�heb vidin punaseks.
Version: 1.3
Author: Help.ee (Andero K, Taavi Larionov, Kaupo Kalda, Veiko J��ger)
Author URI: http://www.help.ee
License: GPL2
*/

// Plugin version
define('VEREMEETER_VERSION', '1.2');

// This URL will always point to the path our plugin files are located
define('VEREMEETER_URL', plugins_url() . '/' . str_replace(basename(__FILE__), '', plugin_basename(__FILE__)));
class Veremeeter extends WP_Widget
{

	function Veremeeter()
	{
		// Register the widget with WP
		$widget_ops = array('classname' => 'Veremeeter', 'description' => __('Help.ee Veremeeter'));
		$this->WP_Widget('veremeeter', __('Help.ee Veremeeter'), $widget_ops);
	}

	function widget($args, $instance)
	{
		global $wpdb;
		extract($args);
		$title = apply_filters('widget_title', empty($instance['title']) ? '&nbsp;' : $instance['title'], $instance, $this->id_base);

		date_default_timezone_set('Eurpoe/Tallinn');

		$impression_count = get_option('veremeeter_impression_count', 0);
		$id = get_option('veremeeter_id', null);

		// Development purposes
		if (null == $id)
		{
			$id = uniqid();
			add_option('veremeeter_id', $id);
		}

		$last_update = get_option('veremeeter_last_update', null);
		if ((time() - $last_update) < 3600)
		{
			$data = get_option('veremeeter_items', null);
		}
		else
		{
			$data = null;
		}

		if ($data)
		{
			$data = unserialize($data);
		}
		else
		{
			$xmlData = @file_get_contents(sprintf('http://www.verekeskus.ee/verekeskus_xml.php?id=%s&imp=%d&uimp=%d&ref=%s%s', urlencode($id), $impression_count, get_option('veremeeter_unique_imptession_count', 0), urlencode($_SERVER['SERVER_NAME']), urlencode($_SERVER['SCRIPT_NAME'])));
			if ($xmlData)
			{
    			$doc = new DOMDocument('1.0', 'utf-8');
    			$doc->loadXML($xmlData);
    			$root = $doc->documentElement;
    			if ($root->getElementsByTagName('blood'))
    			{
    				foreach ($root->getElementsByTagName('blood') as $blood)
    				{
    					$data['blood'][] = array(
    						'level' => $blood->getAttribute('level'),
    						'isCritical' => $blood->getAttribute('isCritical'),
    						'name' => $blood->nodeValue,
    					);
    				}
    			}
    			if ($root->getElementsByTagName('feed'))
    			{
    				foreach ($root->getElementsByTagName('feed') as $feed)
    				{
    					$data['feed'][] = array(
    						'link' => $feed->getAttribute('link'),
    						'title' => $feed->nodeValue,
    					);
    				}
    			}
    			update_option('veremeeter_last_update', time());
    			$last_update = time();
    			update_option('veremeeter_items', serialize($data));
    			$impression_count = 0;
			} else {
			    $data = get_option('veremeeter_items', null);
			    $data = unserialize($data);
			}
		}

		$bloodNameMap = array(
			'0 Rh positiivne' => '0+',
			'0 Rh negatiivne' => '0-',
			'A Rh positiivne' => 'A+',
			'A Rh negatiivne' => 'A-',
			'B Rh positiivne' => 'B+',
			'B Rh negatiivne' => 'B-',
			'AB Rh positiivne' => 'AB+',
			'AB Rh negatiivne' => 'AB-'
		);

		$out = '';
		if (isset($data) && !empty($data))
		{
			if (isset($data['blood']) && !empty($data['blood']))
			{
				$isCritical = false;
				$average = 0;
				$cnt = 0;
				foreach ($data['blood'] as $key => $blood)
				{
					// see rida sisse kui tahad criticale ja vigaseid n�ha
					//$data['blood'][$key]['level'] = $blood['isCritical'] = $data['blood'][$key]['isCritical'] = 1;

					// see blokk sisse kui tahad k�ik ok n�ha
					/*
					$data['blood'][$key]['level'] = 10;
					$blood['level'] = 10;
					$blood['isCritical'] = $data['blood'][$key]['isCritical'] = 0;
					*/

					$average += $blood['level'];
					$cnt++;
					if ($blood['level'] < 7)
					{
						$isCritical = true;
					}
				}
				$average = round($average / (!$cnt ? 80 : $cnt));

				$out .= '<div id="veremeeter">';
				$out .= '<p class="vm-title">Doonorivere seis <a href="http://www.verekeskus.ee" target="_blank">Verekeskuses:</a></p>';


				if ($isCritical)
				{
					foreach ($data['blood'] as $key => $blood)
					{
						if ($blood['level'] > 6)
						{
							continue;
						}
						$out .= '<div class="veremeeter_a clear">';
						$out .= '<div class="veremeeter_b">';
						$out .= '<div class="w100p">';
					    $out .= sprintf('<div class="%s" style="width: %d%%;"></div>', (($blood['level'] < 7 || $blood['isCritical']) ? 'veremeeter_neg' : 'veremeeter_pos'), $blood['level'] * 10);
						$out .= sprintf('<p class="vm-mark"><b>%s</b></p>', (isset($bloodNameMap[$blood['name']]) ? $bloodNameMap[$blood['name']] : $blood['name']));

						if ($blood['isCritical'])
						{
							// this is real critical with blinking text
							$out .= '<blink><p class="vm-help"><a href="http://www.verekeskus.ee" target="_blank" style="text-decoration:underline">Kriitiline!</a></p></blink>';
						}
						else
						{
							// this is not so critical, but still low
							$out .= '<p class="vm-help"><a href="http://www.verekeskus.ee" target="_blank" style="text-decoration:underline">Aita!</a></p>';
						}
						$out .= '</div>';
						$out .= '</div>';
						$out .= '</div>';
					}
				}
				else
				{
					$out .= '<div class="veremeeter_a clear">';
					$out .= '<div class="veremeeter_b">';
					$out .= '<div class="w100p">';
					$out .= sprintf('<div class="%s" style="width: %d%%;"></div>', 'veremeeter_pos', $average * 10);
					//$out .= '</div>';
					$out .= '<p class="vm-mark vm-pos">Olukord hetkel OK</p>';
					$out .= '</div>';
				}
				$out .= '</div>';
				$out .= '<div class="clear">';
				$out .= '<p class="vm_expected">Igapäevane lisa alati oodatud</p>';
				$out .= sprintf('<p class="is_lifestyle"><a href="http://www.help.ee" target="_blank">help.ee</a> - aitamine on elustiil<a href="http://www.help.ee/veremeeter" target="_blank" class="ico_info"><img src="%simg/ico_info.png" title="Viimati uuendatud %s, Kliki ja installi oma Wordpressile Veremeeter." /></a></p>', VEREMEETER_URL, date('d-m-Y H:i:s', $last_update + 2 * 3600));
				$out .= '</div>';
			}
			if (get_option('veremeeter_show_feed', 1) && isset($data['feed']) && !empty($data['feed']))
			{
				$ctr = 0;
				$out .= '<ul class="clear">';
				foreach ($data['feed'] as $key => $value)
				{
					$ctr++;
					$out .= sprintf('<li><a href="%s" target="_bank">%s</a></li>', $value['link'], $value['title']);
					if ($ctr == get_option('veremeeter_feed_items'))
					{
						break;
					}
				}
				$out .= '</ul>';
			}
			$impression_count = intval($impression_count) + 1;
			update_option('veremeeter_impression_count', $impression_count);
		}
		else
		{
			$out = 'No data';
		}
		?>
		<style type="text/css">
			.w100p { width: 100%; }
			.clear { overflow: hidden; clear: both; }
			A IMG { border: none; }
			* HTML .clear { overflow: visible; height: 1px; }
			/*#veremeeter { width: 270px; margin: auto; background: #fff; margin: 100px; padding: 15px; border: 1px solid #CCC; font-weight: 700; display: inline-block; border-radius: 3px; -moz-border-radius: 3px; -webkit-border-radius: 3px; }*/
			#veremeeter_veremeeter A { /*color: #009900;*/ text-decoration: underline; }
			#veremeeter_veremeeter A:hover { /*color: #009900;*/ text-decoration: none; }

			#veremeeter_veremeeter P.vm-mark { margin: -22px 0 0 0; float: left; font-size: 14px; padding: 0 0 0 15px; color: #fff; }
			#veremeeter_veremeeter P.vm-help { margin: -20px 0 0 0; float: right; font-size: 12px; font-weight: bold; padding: 0 10px 0 0; }
			#veremeeter_veremeeter P.vm-help A { color: #c70202; text-decoration: none;}
			#veremeeter_veremeeter P.vm-help A:hover { color: #c70202; text-decoration: underline;}
			#veremeeter_veremeeter P.is_lifestyle { /*color: #666;*/ float: left; margin: 6px 0 0 0; }
			#veremeeter_veremeeter P.vm_expected { margin: 6px 0 0 0; border-bottom: #ccc 1px solid; padding: 0 0 3px 0; }
			#veremeeter_veremeeter P.is_lifestyle A { /*color: #009900;*/ border-bottom: #808080 1px solid; text-decoration: none; }
			#veremeeter_veremeeter P.is_lifestyle A:hover { /*color: #009900;*/ border-bottom: none; text-decoration: none; }
			#veremeeter_veremeeter A.ico_info { float: right; margin: 0; padding: 0 0 0 5px; border-bottom: none !important; }

			#veremeeter_veremeeter .vm-title { margin: 0; /*font-size: 12px; color: #666;*/ }
			#veremeeter_veremeeter .vm-title A { margin: 0; /*color: #009900;*/ font-weight: bold; }
			#veremeeter_veremeeter .veremeeter_a { background: url(<?php echo VEREMEETER_URL; ?>img/bg01.png) no-repeat; height: 23px; margin: 7px 0 0 0; -moz-border-radius: 15px; border-radius: 15px; }
			#veremeeter_veremeeter .veremeeter_b { background: url(<?php echo VEREMEETER_URL; ?>img/bg01.png) 100% -33px no-repeat; height: 23px; padding: 1px; }
			#veremeeter_veremeeter .veremeeter_neg { -moz-border-radius: 15px 0 0 15px; border-radius: 15px; background: url(<?php echo VEREMEETER_URL; ?>img/bg02.png) 100% 0 no-repeat; height: 21px; }
			#veremeeter_veremeeter .veremeeter_pos { -moz-border-radius: 15px 0 0 15px; border-radius: 15px; background: url(<?php echo VEREMEETER_URL; ?>img/bg03.png) 100% 0 no-repeat; height: 21px; }
			#veremeeter_veremeeter p.vm-pos { font-weight: normal; font-size: 12px; margin: -19px 0 0 0; }
			#veremeeter_veremeeter UL { padding: 0; }
		</style>
		<?php
		echo $before_widget;
		echo $before_title . $title . $after_title;
		echo '<div id="veremeeter_wrapper">';
		echo '<div id="veremeeter_veremeeter">';
		echo $out;
		echo '</div>';
		echo '</div>';
		echo $after_widget;
	}

	function unique_visit()
	{
		if (!isset($_COOKIE['veremeeter']))
		{
			$unique_imptession_count = get_option('veremeeter_unique_imptession_count', 0);
			$unique_imptession_count = intval($unique_imptession_count) + 1;
			update_option('veremeeter_unique_imptession_count', $unique_imptession_count);
			setcookie('veremeeter', 1, strtotime('TOMORROW'), COOKIEPATH, COOKIE_DOMAIN);
		}
	}

	function widget_init()
	{
		add_option('veremeeter_show_feed', '1');
		add_option('veremeeter_feed_items', '3');
		add_option('veremeeter_id', uniqid());
		add_option('veremeeter_last_update', 0);
		add_option('impression_count', 0);
		register_widget('Veremeeter');
	}

	function form( $instance )
	{
		// Get the instance settings
		$instance = wp_parse_args( (array) $instance, array( 'title' => '', 'feed_items' => '3', 'show_feed' => 1 ) );
		$title = strip_tags($instance['title']);

		// use_css is loaded from general WP options
		// $use_css = get_option('mc_use_css', '1');
		$feed_items = get_option('veremeeter_feed_items', 3);
		$show_feed = get_option('veremeeter_show_feed', 1);

		// Prepare and show the settings for the admin interface
		$checked = '';
		if ($show_feed == '1') $checked = 'checked="checked"';
		?>
		<p>
			<label for="<?php echo $this->get_field_id('title'); ?>"><?php _e('Title:'); ?></label>
			<input class="widefat" id="<?php echo $this->get_field_id('title'); ?>" name="<?php echo $this->get_field_name('title'); ?>" type="text" value="<?php echo esc_attr($title); ?>" />
		</p>

		<p>
			<input type="checkbox" id="<?php echo $this->get_field_id('show_feed'); ?>" name="<?php echo $this->get_field_name('show_feed'); ?>" value="1" <?php echo $checked; ?> />
			<label for="<?php echo $this->get_field_id('show_feed'); ?>"><?php _e('N&auml;ita uusi help.ee teemasid'); ?></label>
		</p>

		<p>
			<label for="<?php echo $this->get_field_id('feed_items'); ?>"><?php _e('Viimaseid postitusi:'); ?></label>
			<select id="<?php echo $this->get_field_id('feed_items'); ?>" name="<?php echo $this->get_field_name('feed_items'); ?>">
				<?php
				for ($ctr = 1; $ctr < 11; $ctr++)
				{
					printf('<option value="%d"%s>%d</option>', $ctr, (($feed_items == $ctr) ? ' selected="selected"' : ''), $ctr);
				}
				?>
			</select>
		</p>
		<?php
	}
	function update( $new_instance, $old_instance )
	{
		// Save the new widget settings for this instance
		$instance = $old_instance;
		$instance['title'] = strip_tags($new_instance['title']);
		$instance['show_feed'] = strip_tags($new_instance['show_feed']);
		$instance['feed_items'] = strip_tags($new_instance['feed_items']);
		update_option('veremeeter_show_feed', $instance['show_feed']);
		update_option('veremeeter_feed_items', $instance['feed_items']);
		return $instance;
	}

}
function veremeeter_widget_init()
{
	add_option('veremeeter_show_feed', '1');
	add_option('veremeeter_feed_items', '3');
	register_widget('Veremeeter');
}
// FIXME
//error_reporting(E_ALL);
add_action('widgets_init', 'veremeeter_widget_init');
add_action('init', array('Veremeeter', 'unique_visit'));



