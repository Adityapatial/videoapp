<?php
	class Cron_model extends CI_Model{
		
		public function update_username($license, $username){
				$username = strip_tags(trim($username));
 				$this->db->where('license_code', $license);
				return $this->db->update('product_licenses', array('client' => $username));
		}

		public function update_support_date($license, $support_date){
				$new_support_date = date('Y-m-d H:i:s', strtotime(strip_tags(trim($support_date))));
 				$this->db->where('license_code', $license);
				return $this->db->update('product_licenses', array('supported_till' => $new_support_date));
		}

		public function mark_invalid($license){
 				$this->db->where('license_code', $license);
				return $this->db->update('product_licenses', array('validity' => 0));
		}

		public function mark_nonenvato($license){
 				$this->db->where('license_code', $license);
				return $this->db->update('product_licenses', array('is_envato' => 0));
		}

		public function mark_mail_sent($license,$email,$type, $version = null){
 			if(!empty($version)){
 				$data = array(
 				'license' => strip_tags(trim($license)),
				'client_email' => strip_tags(trim($email)),
				'mail_type' => $type,
				'version' => strip_tags(trim($version))
			);
 			}else{
 				$data = array(
 				'license' => strip_tags(trim($license)),
				'client_email' => strip_tags(trim($email)),
				'mail_type' => $type
			);
 			}
			return $this->db->insert('cron_mails', $data);
		}

		public function check_mail_sent($license,$email,$type, $version = null){
			$today = date('Y-m-d H:i:s');
			$this->db->where('license', $license);
			$this->db->where('client_email', $email);
			if(!empty($version)){
				$this->db->where('version', $version);
			}
			$this->db->where('mail_type', $type);
			$this->db->where('date_sent <=', $today);
 			$query = $this->db->get('cron_mails');
			return $query->row_array();
		}

	}