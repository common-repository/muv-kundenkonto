<?php

defined( 'ABSPATH' ) OR exit;

use \muv\KundenKonto\Classes\Tools;

?>
<?php echo $v['text-content']; ?>

--------------------

<?php echo sanitize_textarea_field( Tools::get_option( 'muv-kk-email-vorlage-footer' ) ) ?>
