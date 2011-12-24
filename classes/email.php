<?php defined('SYSPATH') OR die('No direct access allowed.');
/**
 * Email module
 *
 * Ported from Kohana 2.2.3 Core to Kohana 3.0 module
 * 
 * Updated to use Swiftmailer 4.0.4
 *
 * @package    Core
 * @author     Kohana Team
 * @copyright  (c) 2007-2008 Kohana Team
 * @license    http://kohanaphp.com/license.html
 */
class Email 
{

	// SwiftMailer instance
	protected static $mail;

	/**
	 * Creates a SwiftMailer instance.
	 *
	 * @param   string  DSN connection string
	 * @return  object  Swift object
	 */
	public static function connect($config = NULL)
	{
		if ( ! class_exists('Swift_Mailer', FALSE))
		{
			// Load SwiftMailer
			require Kohana::find_file('vendor', 'swift/swift_required');
		}

		// Load default configuration
		($config === NULL) and $config = Kohana::config('email');
		
		switch ($config['driver'])
		{
			case 'smtp':
				// Set port
				$port = empty($config['options']['port']) ? 25 : (int) $config['options']['port'];
				
				// Create SMTP Transport
				$transport = Swift_SmtpTransport::newInstance($config['options']['hostname'], $port);

				if ( ! empty($config['options']['encryption']))
				{
					// Set encryption
					$transport->setEncryption($config['options']['encryption']);
				}
				
				// Do authentication, if part of the DSN
				empty($config['options']['username']) or $transport->setUsername($config['options']['username']);
				empty($config['options']['password']) or $transport->setPassword($config['options']['password']);

				// Set the timeout to 5 seconds
				$transport->setTimeout(empty($config['options']['timeout']) ? 5 : (int) $config['options']['timeout']);
			break;
			case 'sendmail':
				// Create a sendmail connection
				$transport = Swift_SendmailTransport::newInstance(empty($config['options']) ? "/usr/sbin/sendmail -bs" : $config['options']);

			break;
			default:
				// Use the native connection
				$transport = Swift_MailTransport::newInstance($config['options']);
			break;
		}

		// Create the SwiftMailer instance
		return Email::$mail = Swift_Mailer::newInstance($transport);
	}

	/**
	 * Send an email message.
	 *
	 * @param   string|array  recipient email (and name), or an array of To, Cc, Bcc names
	 * @param   string|array  sender email (and name)
	 * @param   string        message subject
	 * @param   string        message body
	 * @param   boolean       send email as HTML
	 * @return  integer       number of emails sent
	 */
	public static function send($to, $from, $subject, $message, $html = FALSE)
	{
		// Connect to SwiftMailer
		(Email::$mail === NULL) and email::connect();

		// Determine the message type
		$html = ($html === TRUE) ? 'text/html' : 'text/plain';

		// Create the message
		$message = Swift_Message::newInstance($subject, $message, $html, 'utf-8');

		if (is_string($to))
		{
			// Single recipient
			$message->setTo($to);
		}
		elseif (is_array($to))
		{
			if (isset($to[0]) AND isset($to[1]))
			{
				// Create To: address set
				$to = array('to' => $to);
			}

			foreach ($to as $method => $set)
			{
				if ( ! in_array($method, array('to', 'cc', 'bcc'), true))
				{
					// Use To: by default
					$method = 'to';
				}

				// Create method name
				$method = 'add'.ucfirst($method);

				if (is_array($set))
				{
					// Add a recipient with name
					$message->$method($set[0], $set[1]);
				}
				else
				{
					// Add a recipient without name
					$message->$method($set);
				}
			}
		}

		if (is_string($from))
		{
			// From without a name
			$message->setFrom($from);
		}
		elseif (is_array($from))
		{
			// From with a name
			$message->setFrom($from[0], $from[1]);
		}

		return Email::$mail->send($message);
	}
	
    public static function send_mail($to, $file, $data = array())
    {
        $from = Kohana::message($file, 'from');
        $subject = Kohana::message($file, 'subject');
        $message = Kohana::message($file, 'html');
        $data_key = array_keys($data);
        $newphrase = str_replace($data_key, $data, $message);        
        $html = true;
        Email::connect($config = NULL);
        Email::send($to, $from, $subject, $newphrase, $html);        
    }

    /**
     * XXX
     * not in use presently because the decorator plugin seems to be buggy.
     * Use templated mail in a loop instead
     * XXX
     * 
     * Method to send a mail using the swiftmail's decorator plugin
     * It uses html from a kohana view file under email/views/
     * and uses settings defined in a Kohana config file email/config/decoratedemail.php
     * @param Array $users array of users with their firstnames and emails
     * @param String $from
     * @param String $subject
     * @param String $body
     * @return Boolean 
     */
    public static function decoratedmail($users, $from, $subject, $body) 
    {
        $settings = Kohana::config('decoratedmail')->as_array();
        // load the base template
        $template = View::factory('email/template')
            ->set('settings', $settings)
            ->set('body', $body);
        $content = $template->render();
        // Connect to SwiftMailer        
        Email::connect($config = NULL);
        (Email::$mail === NULL) and Email::connect();
        // create the associative array to be fed to the decorator plugin
        $replacements = array();
        foreach ($users as $user) {
            $replacements[$user['email']] = array(
                '{firstname}' => $user['firstname'],
                '{email}' => $user['email'],
            );
        }
        // instantiate the decorator plugin and register it to the instance
        $decorator = new Swift_Plugins_DecoratorPlugin($replacements);
        Email::$mail->registerPlugin($decorator);
        // Create the message
        $message = Swift_Message::newInstance()
            ->setSubject($subject)
            ->setBody($content, 'text/html');
        $message->setTo(Arr::pluck($users, 'email'));
        return Email::$mail->send($message);
    }

    public static function templatedmail($users, $from, $subject, $body) {
        $settings = Kohana::config('decoratedmail')->as_array();
        // load the base template
        $template = View::factory('email/template')
            ->set('settings', $settings)
            ->set('body', $body);
        $content = $template->render();
        // Connect to SwiftMailer        
        (Email::$mail === NULL) and Email::connect();
        foreach ($users as $k=>$user) {
            $search = array('{firstname}', '{email}');
            $replace = array($user['firstname'], $user['email']);
            $message = str_replace($search, $replace, $content);            
            echo $message;
            Email::send($user['email'], 'noreply@kodelearn.com', $subject, $message, true);
        }        
    }
} // End email
