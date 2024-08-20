<?php

namespace Bafmaamy\FieldValidator\Plugin;

use Magento\Customer\Api\AddressRepositoryInterface;
use Magento\Customer\Api\Data\AddressInterface;
use Magento\Framework\Exception\InputException;
use Psr\Log\LoggerInterface;

class AddressSavePlugin
{
    private $logger;

    public function __construct(LoggerInterface $logger)
    {
        $this->logger = $logger;
    }

    public function beforeSave(AddressRepositoryInterface $subject, AddressInterface $address)
    {
        try {
            // Validate address fields
            $this->validateInput($address->getCompany(), 'Company');
            $this->validateInput($address->getCity(), 'City');
            $this->validateInput($address->getPostcode(), 'Postcode');
            $this->validateInput($address->getFirstname(), 'First Name');
            $this->validateInput($address->getLastname(), 'Last Name');
            $this->validateInput($address->getTelephone(), 'Phone Number');
             // Validate State/Region
            $region = $address->getRegion();
            if ($region) {
                $this->validateInput($region->getRegion(), 'State/Region Name');
		        $this->validateInput($region->getRegionCode(), 'State/Region Code');
		        $this->validateInput($region->getRegionId(), 'State/Region ID');
	        }
            // Validate each street address line
            $streetLines = $address->getStreet();
            foreach ($streetLines as $key => $streetLine) {
                $this->validateInput($streetLine, 'Street Address Line ' . ($key + 1));
            }
            
        } catch (InputException $e) {
            $this->logAndNotify($e);
            throw $e;  // Re-throw the exception to stop the update process
        }
    }

    private function validateInput($input, $fieldName, $maxLength = null)
    {
        if (empty($input)) {
            return;
        }

        // Add any disallowed characters here
        if (preg_match('/[{}<>%]/', $input)) {
            throw new InputException(__("Invalid characters in $fieldName."));
        }

        if ($maxLength !== null && strlen($input) > $maxLength) {
            throw new InputException(__("$fieldName cannot exceed $maxLength characters."));
        }
    }

    private function logAndNotify(InputException $e)
    {
        $this->logger->warning('Unsuccessful attempt: ' . $e->getMessage(), [
            'IP' => $this->getClientIp(),
            'User Agent' => $_SERVER['HTTP_USER_AGENT'] ?? 'Unknown User Agent',
            'Request URI' => $_SERVER['REQUEST_URI'] ?? 'Unknown URI',
        ]);

        try {
            $this->sendFailureNotification($e);
        } catch (\Exception $emailException) {
            $this->logger->error('Failed to send notification email: ' . $emailException->getMessage());
        }
    }
    private function sendFailureNotification(InputException $exception)
    {
        $message = "Unsuccessful attempt:\n";
        $message .= "Error Message: " . $exception->getMessage() . "\n";
        $message .= "IP: " . $this->getClientIp() . "\n";
        $message .= "User Agent: " . ($_SERVER['HTTP_USER_AGENT'] ?? 'Unknown User Agent') . "\n";
        $message .= "Request URI: " . ($_SERVER['REQUEST_URI'] ?? 'Unknown URI') . "\n";

        // Execute a shell command to send an email
        $command = 'echo "' . addslashes($message) . '" | mailx -s "Unsuccessful attempt" your@email.com';
        exec($command, $output, $returnVar);

        if ($returnVar !== 0) {
            throw new \Exception("Failed to send email. Command output: " . implode("\n", $output));
        }
    }
    private function getClientIp()
    {
        if (!empty($_SERVER['HTTP_CLIENT_IP'])) {
            return $_SERVER['HTTP_CLIENT_IP'];
        } elseif (!empty($_SERVER['HTTP_X_FORWARDED_FOR'])) {
            return $_SERVER['HTTP_X_FORWARDED_FOR'];
        } elseif (!empty($_SERVER['HTTP_X_REAL_IP'])) {
            return $_SERVER['HTTP_X_REAL_IP'];
        } else {
            return $_SERVER['REMOTE_ADDR'];
        }
    }
}
