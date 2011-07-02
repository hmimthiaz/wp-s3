<div class="wrap">
    <?php screen_icon(); ?>
    <h2>Amazon S3 Plugin Configuration</h2>
    <?php if (isset($_GET['msg']) && !empty($_GET['msg'])): ?>
        <div id="s3plugin-settings_updated" class="updated settings-error"> 
    	<p><strong><?php echo $_GET['msg']; ?></strong></p>
        </div>
    <?php endif; ?>
    <form method="post" action="">
	<table class="form-table">
	    <tr valign="top">
		<th scope="row"><label for="s3plugin_amazon_key_id"><?php _e('Plugin Status') ?></label></th>
		<td>
		    <?php if (get_option('s3plugin_enabled', 'inactive') == 'active'): ?>
    		    <strong><?php _e('Active'); ?></strong>
		    <?php else: ?>
    		    <font color="#ff0000"><?php _e('Disabled'); ?></font>
		    <?php endif; ?>
		</td>
	    </tr>
	    <tr valign="top">
		<th scope="row"><label for="s3plugin_amazon_key_id"><?php _e('Amazon Access Key ID') ?></label></th>
		<td>
		    <input name="s3plugin_amazon_key_id" type="text" id="s3plugin_amazon_key_id" value="<?php form_option('s3plugin_amazon_key_id'); ?>" class="regular-text" />
		    <span class="description">
			<a href="https://aws-portal.amazon.com/gp/aws/developer/account/index.html?ie=UTF8&action=access-key" target="_blanck"><?php _e('Amazon S3 Access') ?></a>
		    </span>
		</td>
	    </tr>
	    <tr valign="top">
		<th scope="row"><label for="s3plugin_amazon_secret_key"><?php _e('Amazon Secret Access Key') ?></label></th>
		<td><input name="s3plugin_amazon_secret_key" type="text" id="s3plugin_amazon_secret_key" value="<?php form_option('s3plugin_amazon_secret_key'); ?>" class="regular-text" /></td>
	    </tr>
	    <tr valign="top">
		<th scope="row"><label for="s3plugin_amazon_bucket_name"><?php _e('Amazon Bucket Name') ?></label></th>
		<td>
		    <input name="s3plugin_amazon_bucket_name" type="text" id="s3plugin_amazon_bucket_name" value="<?php form_option('s3plugin_amazon_bucket_name'); ?>" class="regular-text" />
		    <span class="description"><?php _e('This plugin will not create a bucket if it is not there. Please make sure you have already created one with proper ACL permissions.') ?></span>
		</td>
	    </tr>
	    <tr valign="top">
		<th scope="row"><?php _e('Use SSL') ?></th>
		<td>
		    <fieldset>
			<legend class="screen-reader-text"><span><?php _e('Use SSL') ?></span></legend>
			<label for="s3plugin_use_ssl">
			    <input name="s3plugin_use_ssl" type="checkbox" id="s3plugin_use_ssl" value="1" <?php checked('1', get_option('s3plugin_use_ssl')); ?> />
			    <?php _e('Use SSL connection for S3 transfers') ?>
			</label>
		    </fieldset>
		</td>
	    </tr>
	    <tr valign="top">
		<th scope="row"><?php _e('Use Cloudfront URL') ?></th>
		<td>
		    <fieldset>
			<legend class="screen-reader-text"><span><?php _e('Use Cloudfront URL') ?></span></legend>
			<label for="s3plugin_use_cloudfrontURL">
			    <input name="s3plugin_use_cloudfrontURL" type="checkbox" id="s3plugin_use_cloudfrontURL" value="1" <?php checked('1', get_option('s3plugin_use_cloudfrontURL')); ?> />
			    <?php _e('Enable this if you want to use cloudfront URL') ?>
			</label>
		    </fieldset>
		</td>
	    </tr>
	    <tr valign="top">
		<th scope="row"><label for="s3plugin_cloudfrontURL"><?php _e('Amazon Bucket Name') ?></label></th>
		<td>
		    <input name="s3plugin_cloudfrontURL" type="text" id="s3plugin_cloudfrontURL" value="<?php form_option('s3plugin_cloudfrontURL'); ?>" class="regular-text" />
		    <span class="description"><?php _e('Cloudfront URL. This plugin does not invalidate cache at this time.') ?></span>
		</td>
	    </tr>	    
	    <tr valign="top">
		<th scope="row"><label for="s3plugin_cron_interval"><?php _e('Cron Execution Interval') ?></label></th>
		<td>
		    <select name="s3plugin_cron_interval" id="s3plugin_cron_interval">
			<?php
			$selectValue = get_option('s3plugin_cron_interval', 300);
			for ($counter = 300; $counter <= 1800; $counter = $counter + 300) {
			    if ($selectValue == $counter) {
				print "\n\t<option selected='selected' value='{$counter}'>{$counter} seconds</option>";
			    } else {
				print "\n\t<option value='{$counter}'>{$counter} seconds</option>";
			    }
			}
			?>
		    </select>
		    <span class="description"><?php _e('This plugin uses wordpress cron to schedule and upload the files to Amazon S3. Please make sure that yourblog.com/wp-cron.php is setup properly.') ?></span>
		</td>
	    </tr>	
	    <tr valign="top">
		<th scope="row"><label for="s3plugin_cron_limit"><?php _e('Upload Limit') ?></label></th>
		<td>
		    <select name="s3plugin_cron_limit" id="s3plugin_cron_limit">
			<?php
			$selectValue = get_option('s3plugin_cron_limit', 20);
			for ($counter = 10; $counter <= 100; $counter = $counter + 10) {
			    if ($selectValue == $counter) {
				print "\n\t<option selected='selected' value='{$counter}'>{$counter}</option>";
			    } else {
				print "\n\t<option value='{$counter}'>{$counter}</option>";
			    }
			}
			?>
		    </select>
		    <span class="description"><?php _e('No of files to upload on each cron execution.') ?></span>
		</td>
	    </tr>
	    <tr valign="top">
		<th scope="row"><?php _e('Compress JS & CSS') ?></th>
		<td>
		    <fieldset>
			<legend class="screen-reader-text"><span><?php _e('Use SSL') ?></span></legend>
			<label for="s3plugin_compress_files">
			    <input name="s3plugin_compress_files" type="checkbox" id="s3plugin_compress_files" value="1" <?php checked('1', get_option('s3plugin_compress_files')); ?> />
			    <?php _e('Enable this to compress and push js and css files to s3') ?>
			</label>
		    </fieldset>
		</td>
	    </tr>	    
	    <tr valign="top">
		<th scope="row"><label for="s3plugin_cloudfrontURL"><?php _e('Path prefix') ?></label></th>
		<td>
		    <input name="s3plugin_path_prefix" type="text" id="s3plugin_path_prefix" value="<?php form_option('s3plugin_dir_prefix'); ?>" readonly="readonly" size="7" />
		    <span class="description"><?php _e('Path prefix. This will be auto generated when you clear cache.') ?></span>
		</td>
	    </tr>	    
	    <tr valign="top">
		<th scope="row"><?php _e('Clear Cache') ?></th>
		<td>
		    <fieldset>
			<legend class="screen-reader-text"><span><?php _e('Use SSL') ?></span></legend>
			<label for="s3plugin_clear_cache">
			    <input name="s3plugin_clear_cache" type="checkbox" id="s3plugin_clear_cache" value="1" <?php checked('1', get_option('s3plugin_clear_cache')); ?> />
			    <?php _e('Clear Cache. This will change the upload prefix.') ?>
			</label>
		    </fieldset>
		</td>
	    </tr>	    
	    
	</table>
	<p class="submit">
	    <input type="submit" name="Submit" class="button-primary" value="<?php esc_attr_e('Save Changes') ?>" />
	</p>
    </form>
    <p>If you find this plugin usefull please donate few dollars ;-)</p>
    <form action="https://www.paypal.com/cgi-bin/webscr" method="post">
	<input type="hidden" name="cmd" value="_s-xclick">
	<input type="hidden" name="hosted_button_id" value="FMMYTX4JXJHF8">
	<input type="image" src="https://www.paypal.com/en_US/i/btn/btn_donateCC_LG.gif" border="0" name="submit" alt="PayPal - The safer, easier way to pay online!">
	<img alt="" border="0" src="https://www.paypal.com/en_US/i/scr/pixel.gif" width="1" height="1">
    </form>
</div>