<?php
/** @var string $attachment_url
 * @var string $caption
 */
?>

<a class="button" href="<?php echo $attachment_url ?>">
	<img width="14" alt="retrieve-label" src="<?php echo BPOST_PLUGIN_URL ?>/public/images/icon-print-label.png"/>
	<?php
	echo bpost__( 'Show label' );
	?>
</a>

<img id="bpost-logo" alt="bpost-logo" src="<?php echo BPOST_PLUGIN_URL ?>/public/images/bpost-logo.png"/>

<?php
if ( $caption ) {
	echo '<p>' . $caption . '</p>';
}
?>


<div class="clear"></div>