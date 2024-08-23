<?php
namespace Bafmaamy\FieldValidator\Plugin;

use Magento\Sales\Api\OrderRepositoryInterface;
use Magento\Customer\Api\AccountManagementInterface;
use Magento\Customer\Api\CustomerRepositoryInterface;
use Psr\Log\LoggerInterface;
use Magento\Framework\Exception\InputException;

class OrderSourceLogger
{
    protected $logger;

    public function __construct(LoggerInterface $logger)
    {
        $this->logger = $logger;
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

    public function beforeSave(OrderRepositoryInterface $subject, $order)
    {
        // Order-specific logic here...
        $isApiOrder = false;

        // Check if the order is placed via API by inspecting the current request
        if (php_sapi_name() !== 'cli' && isset($_SERVER['HTTP_USER_AGENT'])) {
            $userAgent = $_SERVER['HTTP_USER_AGENT'];
            if (strpos($userAgent, 'REST') !== false || strpos($userAgent, 'API') !== false) {
                $isApiOrder = true;
            }
        }

        try {
            // Validate firstname and lastname with length limit
            $this->validateInput($order->getCustomerFirstname(), 'First Name', 30);
            $this->validateInput($order->getCustomerLastname(), 'Last Name', 30);

            // Validate company and other address fields for disallowed characters
            $billingAddress = $order->getBillingAddress();
            $shippingAddress = $order->getShippingAddress();

            if ($billingAddress) {
                $this->validateInput($billingAddress->getCompany(), 'Billing Company');
                $this->validateInput($billingAddress->getCity(), 'Billing City');
                $this->validateInput($billingAddress->getPostcode(), 'Billing Postcode');
                $this->validateInput($billingAddress->getStreetLine(1), 'Billing Street Address');
		    // Validate State/Region for billing address
            }
            if ($shippingAddress) {
                $this->validateInput($shippingAddress->getCompany(), 'Shipping Company');
                $this->validateInput($shippingAddress->getCity(), 'Shipping City');
                $this->validateInput($shippingAddress->getPostcode(), 'Shipping Postcode');
                $this->validateInput($shippingAddress->getStreetLine(1), 'Shipping Street Address');
		    // Validate State/Region for shipping address
            }

            // Assuming there is a field for order comments
            if ($order->getCustomerNote()) {
                $this->validateInput($order->getCustomerNote(), 'Order Comments');
            }

            // Log the source of the order and additional request details
            $orderSource = $isApiOrder ? 'API' : 'Web';
            $this->logger->info('Order placed via ' . $orderSource . ': Order ID ' . $order->getEntityId());

            $this->logger->info('Order Details', [
                'IP' => $this->getClientIp(),
                'User Agent' => $_SERVER['HTTP_USER_AGENT'] ?? 'Unknown User Agent',
                'Request URI' => $_SERVER['REQUEST_URI'] ?? 'Unknown URI',
            ]);
	    
        } catch (InputException $e) {
            // Log the unsuccessful attempt
            $this->logger->warning('Unsuccessful order attempt: ' . $e->getMessage(), [
                'IP' => $this->getClientIp(),
                'User Agent' => $_SERVER['HTTP_USER_AGENT'] ?? 'Unknown User Agent',
                'Request URI' => $_SERVER['REQUEST_URI'] ?? 'Unknown URI',
            ]);
            $this->logAndNotify($e);
            // Rethrow the exception to stop the order from being saved
            throw $e;
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

        $command = 'echo "' . addslashes($message) . '" | mailx -s "Unsuccessful attempt" your@email.com';
        exec($command, $output, $returnVar);

        if ($returnVar !== 0) {
            throw new \Exception("Failed to send email. Command output: " . implode("\n", $output));
        }
    }

}
