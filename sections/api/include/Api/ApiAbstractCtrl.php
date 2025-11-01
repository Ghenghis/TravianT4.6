<?php

namespace Api;

use Api\Traits\FeatureGateTrait;

class ApiAbstractCtrl
{
    use FeatureGateTrait;
    
    protected $response;
    protected $payload;
    
    public function __construct(&$response, $payload)
    {
        $this->response = &$response;
        $this->payload = &$payload;
        
        $this->initFeatureGate();
    }
    
    /**
     * Set success response
     *
     * @param mixed $data Response data
     * @param string $message Success message
     * @return void
     */
    protected function success($data = null, string $message = 'Success')
    {
        $this->response['status'] = 'success';
        $this->response['message'] = $message;
        if ($data !== null) {
            $this->response['data'] = $data;
        }
    }
    
    /**
     * Set error response
     *
     * @param int $code HTTP status code
     * @param string $message Error message
     * @param string|null $errorCode Error code
     * @return void
     */
    protected function error(int $code, string $message, ?string $errorCode = null)
    {
        $this->setError($code, $message, $errorCode);
    }
}