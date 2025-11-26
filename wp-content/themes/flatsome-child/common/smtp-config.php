<?php
// Setting Smtp websiter
if(function_exists('get_field')){
    add_action('phpmailer_init', function ($phpmailer) {
        if (!is_object($phpmailer))
        $phpmailer = (object) $phpmailer;
        $phpmailer->Mailer     = 'smtp';
        $phpmailer->Host       = get_field('host','option');
        $phpmailer->SMTPAuth   = 1;
        $phpmailer->Port       = get_field('port','option');
        $phpmailer->SMTPSecure = get_field('smtpsecure','option');
        $phpmailer->FromName   = get_field('from_name','option');
        $phpmailer->Username   = get_field('user_name','option');
        $phpmailer->Password   = get_field('password','option');
        $phpmailer->From       = get_field('from','option');
    });
}
