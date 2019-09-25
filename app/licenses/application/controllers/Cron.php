<?php

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

require APPPATH.'third_party/PHPMailer/src/Exception.php';
require APPPATH.'third_party/PHPMailer/src/PHPMailer.php';
require APPPATH.'third_party/PHPMailer/src/SMTP.php';

class Cron extends CI_Controller {
 
    function __construct()
    {
        parent::__construct();
        if (!$this->input->is_cli_request()) show_error('Direct access is not allowed');
    }
 
    public function run()
    { $this->load->helper('api_helper');
       $licenses = $this->licenses_model->get_license();
       $licenses_touched = 0;
       $total_mails_sent = 0;
       $today = date('Y-m-d');
        foreach ($licenses as $license){
            if($license['is_envato']){
                $response = verify_envato_purchase_code_new($license['license_code'],$this->user_model->get_config_from_db('envato_username'),$this->user_model->get_config_from_db('envato_api_token'));
                 if(isset($response['buyer'])&&(strtolower($response['buyer'])==strtolower($license['client']))) {
                    $old_date = new DateTime($license['supported_till'], new DateTimeZone(date_default_timezone_get()));
                    $old_date->setTimezone(new DateTimeZone('Australia/Brisbane'));
                    $old_date_envato = $old_date->format('c');
                    if($old_date_envato!=$response['supported_until']){
                        $this->cron_model->update_support_date($license['license_code'],$response['supported_until']);
                        $this->user_model->add_log('Envato has updated client <b>'.$license['client'].'</b>\'s purchase code <b>'.$license['license_code'].'</b> support date, client may have renewed support, therefore the support date for this license was updated.');
                        $licenses_touched++;
                    }
                 }elseif(isset($response['buyer'])&&$response['buyer']!=$license['client']){
                 	$this->cron_model->update_username($license['license_code'],$response['buyer']);
                    $this->installations_model->change_activation_client($license['license_code'],$license['client'],$response['buyer']);
                 	$this->user_model->add_log('Envato client <b>'.$license['client'].'</b> appears to have changed their envato username to <b>'.$response['buyer'].'</b>, therefore their license was modified to include new username.');
                    $licenses_touched++;
                 }
                 else{
                   	$this->cron_model->mark_invalid($license['license_code']);
                    $this->cron_model->mark_nonenvato($license['license_code']);
                    $this->user_model->add_log('Envato has marked client <b>'.$license['client'].'</b>\'s purchase code <b>'.$license['license_code'].'</b> as invalid, there may have been a sale reversal or a refund, therefore this license is now marked as invalid.');
                    $licenses_touched++;
                 }
            }

            if(!empty($license['email'])){
        
            $mail_type = null;
            $is_mail_sent = false;

            if($this->user_model->get_config_from_db('license_expiring_enable')==1){
                if(!empty($license['expiry'])){
                $last_sent_mail = $this->cron_model->check_mail_sent($license['license_code'],$license['email'], 'license_expiring');
                if(($today==date('Y-m-d', strtotime($license['expiry'])))&&empty($last_sent_mail)){
                    $mail_type = "license_expiring";
                    $is_mail_sent = $this->send_mail_to_client($mail_type,$license);
                    if($is_mail_sent){
                    $total_mails_sent++;
                    $this->user_model->add_log('Automated license expiring email sent to client <b>'.$license['client'].'</b> having email address <b>'.$license['email'].'</b>.');
                        if($mail_type=='new_update'){
                        $this->cron_model->mark_mail_sent($license['license_code'], $license['email'], $mail_type, $latest_version);
                        }else{
                        $this->cron_model->mark_mail_sent($license['license_code'], $license['email'], $mail_type);
                        }
                    }
                }
            }
            }

            if($this->user_model->get_config_from_db('support_expiring_enable')==1){
                if(!empty($license['supported_till'])){
                $last_sent_mail = $this->cron_model->check_mail_sent($license['license_code'],$license['email'], 'support_expiring');
                if(($today==date('Y-m-d', strtotime($license['supported_till'])))&&empty($last_sent_mail)){
                    $mail_type = "support_expiring";
                   $is_mail_sent = $this->send_mail_to_client($mail_type,$license);
                    if($is_mail_sent){
                    $total_mails_sent++;
                    $this->user_model->add_log('Automated support expiring email sent to client <b>'.$license['client'].'</b> having email address <b>'.$license['email'].'</b>.');
                        if($mail_type=='new_update'){
                        $this->cron_model->mark_mail_sent($license['license_code'], $license['email'], $mail_type, $latest_version);
                        }else{
                        $this->cron_model->mark_mail_sent($license['license_code'], $license['email'], $mail_type);
                        }
                    }
                }
            }
            }

            if($this->user_model->get_config_from_db('updates_expiring_enable')==1){
                if(!empty($license['updates_till'])){
                $last_sent_mail = $this->cron_model->check_mail_sent($license['license_code'],$license['email'], 'updates_expiring');
                if(($today==date('Y-m-d', strtotime($license['updates_till'])))&&empty($last_sent_mail)){
                    $mail_type = "updates_expiring";
                   $is_mail_sent = $this->send_mail_to_client($mail_type,$license);
                    if($is_mail_sent){
                    $total_mails_sent++;
                    $this->user_model->add_log('Automated updates expiring email sent to client <b>'.$license['client'].'</b> having email address <b>'.$license['email'].'</b>.');
                        if($mail_type=='new_update'){
                        $this->cron_model->mark_mail_sent($license['license_code'], $license['email'], $mail_type, $latest_version);
                        }else{
                        $this->cron_model->mark_mail_sent($license['license_code'], $license['email'], $mail_type);
                        }
                    }
                }
            }
            }

            if($this->user_model->get_config_from_db('new_update_enable')==1){
                $res_latest_version = $this->products_model->get_latest_version($license['pid']);
                if(!empty($res_latest_version)){
                        $latest_version = $res_latest_version[0]['version'];
                    }else{
                        $latest_version = null;
                    }
                if(!empty($latest_version)){
                $last_sent_mail = $this->cron_model->check_mail_sent($license['license_code'],$license['email'], 'new_update', $latest_version);
                if(!empty($license['updates_till'])){
                if(($today>=date('Y-m-d', strtotime($license['updates_till'])))&&empty($last_sent_mail)){
                    $update_valid = false;
                }elseif(empty($last_sent_mail)){
                    $update_valid = true;
                }else{
                    $update_valid = false;
                }
            }elseif(empty($last_sent_mail)){
                $update_valid = true;
            }else{
                $update_valid = false;
            }
                }else{
                $update_valid = false;
            }
                if($update_valid)
                {
                    $mail_type = "new_update";
                    $is_mail_sent = $this->send_mail_to_client($mail_type,$license);
                    if($is_mail_sent){
                    $total_mails_sent++;
                    $this->user_model->add_log('Automated new update released email sent to client <b>'.$license['client'].'</b> having email address <b>'.$license['email'].'</b>.');
                        if($mail_type=='new_update'){
                        $this->cron_model->mark_mail_sent($license['license_code'], $license['email'], $mail_type, $latest_version);
                        }else{
                        $this->cron_model->mark_mail_sent($license['license_code'], $license['email'], $mail_type);
                        }
                    }
                }
            }
        }else{
            $is_mail_sent = false;
        }
        }

        $cr_response = "Cron (".date('Y-m-d H:i:s').") execution completed, brief summary: \r\n ";
        if($licenses_touched<=0){
            $cr_response.="no envato licenses were found to be out-of-sync.";
        }elseif($licenses_touched==1){
            $cr_response.=$licenses_touched." envato license was out-of-sync and was updated.";
        }else{
            $cr_response.=$licenses_touched." envato licenses were out-of-sync and updated.";
        }
        $cr_response.=" \r\n ";
        if($total_mails_sent<=0){
            $cr_response.="no automated emails were sent to clients.";
        }elseif($total_mails_sent==1){
            $cr_response.=$total_mails_sent." automated email was sent to client.";
        }else{
            $cr_response.=$total_mails_sent." automated emails were sent to clients.";
        }

        echo $cr_response;
    }


        public function send_mail_to_client($mail_type,$license){
        if(!empty($mail_type)){
            $mail = new PHPMailer();
            $today = date('Y-m-d');
            try {

            // If using SMTP, uncomment this code and add your mail host,username and password   
            /*$mail->isSMTP();                              
            $mail->Host = 'smtp.gmail.com'; 
            $mail->SMTPAuth = true;                              
            $mail->Username = '';           
            $mail->Password = '';                        
            $mail->SMTPSecure = 'tls';                 
            $mail->Port = 587;*/
            // SMTP End                                     

            $product = $this->products_model->get_product($license['pid']);
            $res_latest_version = $this->products_model->get_latest_version($license['pid']);
            if(!empty($res_latest_version)){
                $latest_version = $res_latest_version[0]['version'];
            }else{
                $latest_version = null;
            }
            $changelog = $res_latest_version[0]['changelog'];

            $mail->setFrom($this->user_model->get_config_from_db('server_email'));
            $mail->addAddress($license['email'], ucfirst($license['client']));    
            $mail->isHTML(true); 

            $mail_subject = '{[product]} - Information'; 
            $trans00 = array("{[product]}" => ucfirst($product['pd_name']));       
            $mail_subject_final = strtr($mail_subject, $trans00);            
            $trans0 = array('{[email_format]}' => $this->user_model->get_config_from_db($mail_type)); 
            $trans = array("{[subject]}" => $mail_subject_final, "{[product]}" => ucwords($product['pd_name']), "{[client]}" => ucwords($license['client']), "{[version]}" => $latest_version, "{[changelog]}" => $changelog); 
            $mail_final0 = strtr("<!DOCTYPE html PUBLIC '-//W3C//DTD XHTML 1.0 Transitional//EN' 'http://www.w3.org/TR/xhtml1/DTD/xhtml1-transitional.dtd'><html xmlns='http://www.w3.org/1999/xhtml'><head><meta http-equiv='Content-Type' content='text/html; charset=utf-8'/><title>{[subject]}</title><style type='text/css'>body{padding-top: 0 !important;padding-bottom: 0 !important;padding-top: 0 !important;padding-bottom: 0 !important;margin: 0 !important;width: 100% !important;-webkit-text-size-adjust: 100% !important;-ms-text-size-adjust: 100% !important;-webkit-font-smoothing: antialiased !important}.footer-text{color: #382F2E;}.tableContent a{color: #382F2E}p,h1{color: #382F2E;margin: 0}p{text-align: left;color: #5f6368;font-size: 16px;font-weight: normal;padding-top: 12px}a.link1{color: #382F2E;text-decoration: none}a.link2{font-size: 16px;text-decoration: none;color: #fff}h2{text-align: left;color: #222;font-size: 19px;font-weight: normal}div,p,ul,h1{margin: 0}.bgBody{background: #fff}.bgItem{background: #fff}@media only screen and (max-width:480px){table[class='MainContainer'],td[class='cell']{width: 100% !important;height: auto !important}td[class='specbundle']{width: 100% !important;float: left !important;font-size: 13px !important;line-height: 17px !important;display: block !important;padding-bottom: 15px !important}td[class='spechide']{display: none !important}img[class='banner']{width: 100% !important;height: auto !important}td[class='left_pad']{padding-left: 15px !important;padding-right: 15px !important}}@media only screen and (max-width:540px){table[class='MainContainer'],td[class='cell']{width: 100% !important;height: auto !important}td[class='specbundle']{width: 100% !important;float: left !important;font-size: 13px !important;line-height: 17px !important;display: block !important;padding-bottom: 15px !important}td[class='spechide']{display: none !important}img[class='banner']{width: 100% !important;height: auto !important}.font{font-size: 18px !important;line-height: 22px !important}.font1{font-size: 18px !important;line-height: 22px !important}}</style></head><body paddingwidth='0' paddingheight='0' style='padding-top: 0; padding-bottom: 0; padding-top: 0; padding-bottom: 0; background-repeat: repeat; width: 100% !important; -webkit-text-size-adjust: 100%; -ms-text-size-adjust: 100%; -webkit-font-smoothing: antialiased;' offset='0' toppadding='0' leftpadding='0'><table bgcolor='#ffffff' width='100%' border='0' cellspacing='0' cellpadding='0' class='tableContent' align='center' style='font-family:Helvetica, Arial,serif;'><tbody><tr><td><table width='600' border='0' cellspacing='0' cellpadding='0' align='center' bgcolor='#ffffff' class='MainContainer'><tbody><tr><td><table width='100%' border='0' cellspacing='0' cellpadding='0'><tbody><tr><td valign='top' width='40'>&nbsp;</td><td><table width='100%' border='0' cellspacing='0' cellpadding='0'><tbody><tr><td height='75' class='spechide'></td></tr><tr><td class='movableContentContainer ' valign='top'><div class='movableContent' style='border: 0px; padding-top: 0px; position: relative;'><table width='100%' border='0' cellspacing='0' cellpadding='0'> <tbody> <tr> <td height='35'></td></tr><tr> <td> <p style='text-align:center;margin:0;font-family:Georgia,Time,sans-serif;font-size:26px;color:#222222;'><span class='specbundle2'><span class='font1'>{[subject]}</span></span> </p></td></tr></tbody></table></div><div class='movableContent' style='border: 0px; padding-top: 0px; position: relative;'><br><br><div>{[email_format]}</div></div><div class='movableContent' style='border: 0px; padding-top: 0px; position: relative;'><table width='100%' border='0' cellspacing='0' cellpadding='0'> <tbody> <tr> <td height='35'> </tr><tr> <td style='border-bottom:1px solid #DDDDDD;'></td></tr><tr> <td height='25'></td></tr><tr> <td style='font-size:12px;'> <center><span class='footer-text'>Copyright 2018, All Rights Reserved.</center></span></td></tr><tr> <td height='88'></td></tr></tbody></table> </div></td></tr></tbody></table></td><td valign='top' width='40'>&nbsp;</td></tr></tbody></table></td></tr></tbody></table></td></tr></tbody></table></body></html>", $trans0);

            $altmail_final0 = strtr("{[email_format]}", $trans0);

            $mail->Subject = $mail_subject_final;
            $mail->Body =  strtr($mail_final0, $trans);
            $mail->AltBody = strtr($altmail_final0, $trans);
            $is_mail_sent = true;
                if(!$mail->send()){
                    $is_mail_sent = false;
                }
            } catch (Exception $e) {
                    $is_mail_sent = false;
            }   
            }else{
                $is_mail_sent = false;
            }
            return $is_mail_sent;
    }
}