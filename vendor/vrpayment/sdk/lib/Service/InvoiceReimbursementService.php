<?php
/**
 * VRPay SDK
 *
 * This library allows to interact with the VRPay payment service.
 *
 * Licensed under the Apache License, Version 2.0 (the "License");
 * you may not use this file except in compliance with the License.
 * You may obtain a copy of the License at
 *
 *      http://www.apache.org/licenses/LICENSE-2.0
 *
 * Unless required by applicable law or agreed to in writing, software
 * distributed under the License is distributed on an "AS IS" BASIS,
 * WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND, either express or implied.
 * See the License for the specific language governing permissions and
 * limitations under the License.
 */


namespace VRPayment\Sdk\Service;

use VRPayment\Sdk\ApiClient;
use VRPayment\Sdk\ApiException;
use VRPayment\Sdk\ApiResponse;
use VRPayment\Sdk\Http\HttpRequest;
use VRPayment\Sdk\ObjectSerializer;

/**
 * InvoiceReimbursementService service
 *
 * @category Class
 * @package  VRPayment\Sdk
 * @author   VR Payment GmbH
 * @license  http://www.apache.org/licenses/LICENSE-2.0 Apache License v2
 */
class InvoiceReimbursementService {

	/**
	 * The API client instance.
	 *
	 * @var ApiClient
	 */
	private $apiClient;

	/**
	 * Constructor.
	 *
	 * @param ApiClient $apiClient the api client
	 */
	public function __construct(ApiClient $apiClient) {
		if (is_null($apiClient)) {
			throw new \InvalidArgumentException('The api client is required.');
		}

		$this->apiClient = $apiClient;
	}

	/**
	 * Returns the API client instance.
	 *
	 * @return ApiClient
	 */
	public function getApiClient() {
		return $this->apiClient;
	}


	/**
	 * Operation count
	 *
	 * Count
	 *
	 * @param int $space_id  (required)
	 * @param \VRPayment\Sdk\Model\EntityQueryFilter $filter The filter which restricts the entities which are used to calculate the count. (optional)
	 * @throws \VRPayment\Sdk\ApiException
	 * @throws \VRPayment\Sdk\VersioningException
	 * @throws \VRPayment\Sdk\Http\ConnectionException
	 * @return int
	 */
	public function count($space_id, $filter = null) {
		return $this->countWithHttpInfo($space_id, $filter)->getData();
	}

	/**
	 * Operation countWithHttpInfo
	 *
	 * Count
     
     *
	 * @param int $space_id  (required)
	 * @param \VRPayment\Sdk\Model\EntityQueryFilter $filter The filter which restricts the entities which are used to calculate the count. (optional)
	 * @throws \VRPayment\Sdk\ApiException
	 * @throws \VRPayment\Sdk\VersioningException
	 * @throws \VRPayment\Sdk\Http\ConnectionException
	 * @return ApiResponse
	 */
	public function countWithHttpInfo($space_id, $filter = null) {
		// verify the required parameter 'space_id' is set
		if (is_null($space_id)) {
			throw new \InvalidArgumentException('Missing the required parameter $space_id when calling count');
		}
		// header params
		$headerParams = [];
		$headerAccept = $this->apiClient->selectHeaderAccept(['application/json;charset=utf-8']);
		if (!is_null($headerAccept)) {
			$headerParams[HttpRequest::HEADER_KEY_ACCEPT] = $headerAccept;
		}
		$headerParams[HttpRequest::HEADER_KEY_CONTENT_TYPE] = $this->apiClient->selectHeaderContentType(['application/json;charset=utf-8']);

		// query params
		$queryParams = [];
		if (!is_null($space_id)) {
			$queryParams['spaceId'] = $this->apiClient->getSerializer()->toQueryValue($space_id);
		}

		// path params
		$resourcePath = '/invoice-reimbursement-service/count';
		// default format to json
		$resourcePath = str_replace('{format}', 'json', $resourcePath);

		// form params
		$formParams = [];
		// body params
		$tempBody = null;
		if (isset($filter)) {
			$tempBody = $filter;
		}

		// for model (json/xml)
		$httpBody = '';
		if (isset($tempBody)) {
			$httpBody = $tempBody; // $tempBody is the method argument, if present
		} elseif (!empty($formParams)) {
			$httpBody = $formParams; // for HTTP post (form)
		}
		// make the API Call
		try {
			$response = $this->apiClient->callApi(
				$resourcePath,
				'POST',
				$queryParams,
				$httpBody,
				$headerParams,
				'int',
				'/invoice-reimbursement-service/count'
            );
			return new ApiResponse($response->getStatusCode(), $response->getHeaders(), $this->apiClient->getSerializer()->deserialize($response->getData(), 'int', $response->getHeaders()));
		} catch (ApiException $e) {
			switch ($e->getCode()) {
                case 200:
                    $data = ObjectSerializer::deserialize(
                        $e->getResponseBody(),
                        'int',
                        $e->getResponseHeaders()
                    );
                    $e->setResponseObject($data);
                break;
                case 442:
                    $data = ObjectSerializer::deserialize(
                        $e->getResponseBody(),
                        '\VRPayment\Sdk\Model\ClientError',
                        $e->getResponseHeaders()
                    );
                    $e->setResponseObject($data);
                break;
                case 542:
                    $data = ObjectSerializer::deserialize(
                        $e->getResponseBody(),
                        '\VRPayment\Sdk\Model\ServerError',
                        $e->getResponseHeaders()
                    );
                    $e->setResponseObject($data);
                break;
			}
			throw $e;
		}
	}

	/**
	 * Operation read
	 *
	 * Read
	 *
	 * @param int $space_id  (required)
	 * @param int $id The ID of the invoice reimbursement which should be returned. (required)
	 * @throws \VRPayment\Sdk\ApiException
	 * @throws \VRPayment\Sdk\VersioningException
	 * @throws \VRPayment\Sdk\Http\ConnectionException
	 * @return \VRPayment\Sdk\Model\InvoiceReimbursement
	 */
	public function read($space_id, $id) {
		return $this->readWithHttpInfo($space_id, $id)->getData();
	}

	/**
	 * Operation readWithHttpInfo
	 *
	 * Read
     
     *
	 * @param int $space_id  (required)
	 * @param int $id The ID of the invoice reimbursement which should be returned. (required)
	 * @throws \VRPayment\Sdk\ApiException
	 * @throws \VRPayment\Sdk\VersioningException
	 * @throws \VRPayment\Sdk\Http\ConnectionException
	 * @return ApiResponse
	 */
	public function readWithHttpInfo($space_id, $id) {
		// verify the required parameter 'space_id' is set
		if (is_null($space_id)) {
			throw new \InvalidArgumentException('Missing the required parameter $space_id when calling read');
		}
		// verify the required parameter 'id' is set
		if (is_null($id)) {
			throw new \InvalidArgumentException('Missing the required parameter $id when calling read');
		}
		// header params
		$headerParams = [];
		$headerAccept = $this->apiClient->selectHeaderAccept(['application/json;charset=utf-8']);
		if (!is_null($headerAccept)) {
			$headerParams[HttpRequest::HEADER_KEY_ACCEPT] = $headerAccept;
		}
		$headerParams[HttpRequest::HEADER_KEY_CONTENT_TYPE] = $this->apiClient->selectHeaderContentType(['*/*']);

		// query params
		$queryParams = [];
		if (!is_null($space_id)) {
			$queryParams['spaceId'] = $this->apiClient->getSerializer()->toQueryValue($space_id);
		}
		if (!is_null($id)) {
			$queryParams['id'] = $this->apiClient->getSerializer()->toQueryValue($id);
		}

		// path params
		$resourcePath = '/invoice-reimbursement-service/read';
		// default format to json
		$resourcePath = str_replace('{format}', 'json', $resourcePath);

		// form params
		$formParams = [];
		
		// for model (json/xml)
		$httpBody = '';
		if (isset($tempBody)) {
			$httpBody = $tempBody; // $tempBody is the method argument, if present
		} elseif (!empty($formParams)) {
			$httpBody = $formParams; // for HTTP post (form)
		}
		// make the API Call
		try {
			$response = $this->apiClient->callApi(
				$resourcePath,
				'GET',
				$queryParams,
				$httpBody,
				$headerParams,
				'\VRPayment\Sdk\Model\InvoiceReimbursement',
				'/invoice-reimbursement-service/read'
            );
			return new ApiResponse($response->getStatusCode(), $response->getHeaders(), $this->apiClient->getSerializer()->deserialize($response->getData(), '\VRPayment\Sdk\Model\InvoiceReimbursement', $response->getHeaders()));
		} catch (ApiException $e) {
			switch ($e->getCode()) {
                case 200:
                    $data = ObjectSerializer::deserialize(
                        $e->getResponseBody(),
                        '\VRPayment\Sdk\Model\InvoiceReimbursement',
                        $e->getResponseHeaders()
                    );
                    $e->setResponseObject($data);
                break;
                case 442:
                    $data = ObjectSerializer::deserialize(
                        $e->getResponseBody(),
                        '\VRPayment\Sdk\Model\ClientError',
                        $e->getResponseHeaders()
                    );
                    $e->setResponseObject($data);
                break;
                case 542:
                    $data = ObjectSerializer::deserialize(
                        $e->getResponseBody(),
                        '\VRPayment\Sdk\Model\ServerError',
                        $e->getResponseHeaders()
                    );
                    $e->setResponseObject($data);
                break;
			}
			throw $e;
		}
	}

	/**
	 * Operation search
	 *
	 * Search
	 *
	 * @param int $space_id  (required)
	 * @param \VRPayment\Sdk\Model\EntityQuery $query The query restricts the invoice reimbursements which are returned by the search. (required)
	 * @throws \VRPayment\Sdk\ApiException
	 * @throws \VRPayment\Sdk\VersioningException
	 * @throws \VRPayment\Sdk\Http\ConnectionException
	 * @return \VRPayment\Sdk\Model\InvoiceReimbursementWithRefundReference[]
	 */
	public function search($space_id, $query) {
		return $this->searchWithHttpInfo($space_id, $query)->getData();
	}

	/**
	 * Operation searchWithHttpInfo
	 *
	 * Search
     
     *
	 * @param int $space_id  (required)
	 * @param \VRPayment\Sdk\Model\EntityQuery $query The query restricts the invoice reimbursements which are returned by the search. (required)
	 * @throws \VRPayment\Sdk\ApiException
	 * @throws \VRPayment\Sdk\VersioningException
	 * @throws \VRPayment\Sdk\Http\ConnectionException
	 * @return ApiResponse
	 */
	public function searchWithHttpInfo($space_id, $query) {
		// verify the required parameter 'space_id' is set
		if (is_null($space_id)) {
			throw new \InvalidArgumentException('Missing the required parameter $space_id when calling search');
		}
		// verify the required parameter 'query' is set
		if (is_null($query)) {
			throw new \InvalidArgumentException('Missing the required parameter $query when calling search');
		}
		// header params
		$headerParams = [];
		$headerAccept = $this->apiClient->selectHeaderAccept(['application/json;charset=utf-8']);
		if (!is_null($headerAccept)) {
			$headerParams[HttpRequest::HEADER_KEY_ACCEPT] = $headerAccept;
		}
		$headerParams[HttpRequest::HEADER_KEY_CONTENT_TYPE] = $this->apiClient->selectHeaderContentType(['application/json;charset=utf-8']);

		// query params
		$queryParams = [];
		if (!is_null($space_id)) {
			$queryParams['spaceId'] = $this->apiClient->getSerializer()->toQueryValue($space_id);
		}

		// path params
		$resourcePath = '/invoice-reimbursement-service/search';
		// default format to json
		$resourcePath = str_replace('{format}', 'json', $resourcePath);

		// form params
		$formParams = [];
		// body params
		$tempBody = null;
		if (isset($query)) {
			$tempBody = $query;
		}

		// for model (json/xml)
		$httpBody = '';
		if (isset($tempBody)) {
			$httpBody = $tempBody; // $tempBody is the method argument, if present
		} elseif (!empty($formParams)) {
			$httpBody = $formParams; // for HTTP post (form)
		}
		// make the API Call
		try {
			$response = $this->apiClient->callApi(
				$resourcePath,
				'POST',
				$queryParams,
				$httpBody,
				$headerParams,
				'\VRPayment\Sdk\Model\InvoiceReimbursementWithRefundReference[]',
				'/invoice-reimbursement-service/search'
            );
			return new ApiResponse($response->getStatusCode(), $response->getHeaders(), $this->apiClient->getSerializer()->deserialize($response->getData(), '\VRPayment\Sdk\Model\InvoiceReimbursementWithRefundReference[]', $response->getHeaders()));
		} catch (ApiException $e) {
			switch ($e->getCode()) {
                case 200:
                    $data = ObjectSerializer::deserialize(
                        $e->getResponseBody(),
                        '\VRPayment\Sdk\Model\InvoiceReimbursementWithRefundReference[]',
                        $e->getResponseHeaders()
                    );
                    $e->setResponseObject($data);
                break;
                case 442:
                    $data = ObjectSerializer::deserialize(
                        $e->getResponseBody(),
                        '\VRPayment\Sdk\Model\ClientError',
                        $e->getResponseHeaders()
                    );
                    $e->setResponseObject($data);
                break;
                case 542:
                    $data = ObjectSerializer::deserialize(
                        $e->getResponseBody(),
                        '\VRPayment\Sdk\Model\ServerError',
                        $e->getResponseHeaders()
                    );
                    $e->setResponseObject($data);
                break;
			}
			throw $e;
		}
	}

	/**
	 * Operation updateConnector
	 *
	 * Update payment connector configuration
	 *
	 * @param int $space_id  (required)
	 * @param int $id The ID of the invoice reimbursement of which connector should be updated. (required)
	 * @param int $payment_connector_configuration_id  (required)
	 * @throws \VRPayment\Sdk\ApiException
	 * @throws \VRPayment\Sdk\VersioningException
	 * @throws \VRPayment\Sdk\Http\ConnectionException
	 * @return void
	 */
	public function updateConnector($space_id, $id, $payment_connector_configuration_id) {
		return $this->updateConnectorWithHttpInfo($space_id, $id, $payment_connector_configuration_id)->getData();
	}

	/**
	 * Operation updateConnectorWithHttpInfo
	 *
	 * Update payment connector configuration
     
     *
	 * @param int $space_id  (required)
	 * @param int $id The ID of the invoice reimbursement of which connector should be updated. (required)
	 * @param int $payment_connector_configuration_id  (required)
	 * @throws \VRPayment\Sdk\ApiException
	 * @throws \VRPayment\Sdk\VersioningException
	 * @throws \VRPayment\Sdk\Http\ConnectionException
	 * @return ApiResponse
	 */
	public function updateConnectorWithHttpInfo($space_id, $id, $payment_connector_configuration_id) {
		// verify the required parameter 'space_id' is set
		if (is_null($space_id)) {
			throw new \InvalidArgumentException('Missing the required parameter $space_id when calling updateConnector');
		}
		// verify the required parameter 'id' is set
		if (is_null($id)) {
			throw new \InvalidArgumentException('Missing the required parameter $id when calling updateConnector');
		}
		// verify the required parameter 'payment_connector_configuration_id' is set
		if (is_null($payment_connector_configuration_id)) {
			throw new \InvalidArgumentException('Missing the required parameter $payment_connector_configuration_id when calling updateConnector');
		}
		// header params
		$headerParams = [];
		$headerAccept = $this->apiClient->selectHeaderAccept([]);
		if (!is_null($headerAccept)) {
			$headerParams[HttpRequest::HEADER_KEY_ACCEPT] = $headerAccept;
		}
		$headerParams[HttpRequest::HEADER_KEY_CONTENT_TYPE] = $this->apiClient->selectHeaderContentType([]);

		// query params
		$queryParams = [];
		if (!is_null($space_id)) {
			$queryParams['spaceId'] = $this->apiClient->getSerializer()->toQueryValue($space_id);
		}
		if (!is_null($id)) {
			$queryParams['id'] = $this->apiClient->getSerializer()->toQueryValue($id);
		}
		if (!is_null($payment_connector_configuration_id)) {
			$queryParams['paymentConnectorConfigurationId'] = $this->apiClient->getSerializer()->toQueryValue($payment_connector_configuration_id);
		}

		// path params
		$resourcePath = '/invoice-reimbursement-service/update-connector';
		// default format to json
		$resourcePath = str_replace('{format}', 'json', $resourcePath);

		// form params
		$formParams = [];
		
		// for model (json/xml)
		$httpBody = '';
		if (isset($tempBody)) {
			$httpBody = $tempBody; // $tempBody is the method argument, if present
		} elseif (!empty($formParams)) {
			$httpBody = $formParams; // for HTTP post (form)
		}
		// make the API Call
		try {
			$response = $this->apiClient->callApi(
				$resourcePath,
				'POST',
				$queryParams,
				$httpBody,
				$headerParams,
				null,
				'/invoice-reimbursement-service/update-connector'
            );
			return new ApiResponse($response->getStatusCode(), $response->getHeaders());
		} catch (ApiException $e) {
			switch ($e->getCode()) {
                case 409:
                    $data = ObjectSerializer::deserialize(
                        $e->getResponseBody(),
                        '\VRPayment\Sdk\Model\ClientError',
                        $e->getResponseHeaders()
                    );
                    $e->setResponseObject($data);
                break;
                case 442:
                    $data = ObjectSerializer::deserialize(
                        $e->getResponseBody(),
                        '\VRPayment\Sdk\Model\ClientError',
                        $e->getResponseHeaders()
                    );
                    $e->setResponseObject($data);
                break;
                case 542:
                    $data = ObjectSerializer::deserialize(
                        $e->getResponseBody(),
                        '\VRPayment\Sdk\Model\ServerError',
                        $e->getResponseHeaders()
                    );
                    $e->setResponseObject($data);
                break;
			}
			throw $e;
		}
	}

	/**
	 * Operation updateIban
	 *
	 * Update IBAN
	 *
	 * @param int $space_id  (required)
	 * @param int $id The ID of the invoice reimbursement of which IBANs should be updated. (required)
	 * @param string $recipient_iban  (optional)
	 * @param string $sender_iban  (optional)
	 * @throws \VRPayment\Sdk\ApiException
	 * @throws \VRPayment\Sdk\VersioningException
	 * @throws \VRPayment\Sdk\Http\ConnectionException
	 * @return void
	 */
	public function updateIban($space_id, $id, $recipient_iban = null, $sender_iban = null) {
		return $this->updateIbanWithHttpInfo($space_id, $id, $recipient_iban, $sender_iban)->getData();
	}

	/**
	 * Operation updateIbanWithHttpInfo
	 *
	 * Update IBAN
     
     *
	 * @param int $space_id  (required)
	 * @param int $id The ID of the invoice reimbursement of which IBANs should be updated. (required)
	 * @param string $recipient_iban  (optional)
	 * @param string $sender_iban  (optional)
	 * @throws \VRPayment\Sdk\ApiException
	 * @throws \VRPayment\Sdk\VersioningException
	 * @throws \VRPayment\Sdk\Http\ConnectionException
	 * @return ApiResponse
	 */
	public function updateIbanWithHttpInfo($space_id, $id, $recipient_iban = null, $sender_iban = null) {
		// verify the required parameter 'space_id' is set
		if (is_null($space_id)) {
			throw new \InvalidArgumentException('Missing the required parameter $space_id when calling updateIban');
		}
		// verify the required parameter 'id' is set
		if (is_null($id)) {
			throw new \InvalidArgumentException('Missing the required parameter $id when calling updateIban');
		}
		// header params
		$headerParams = [];
		$headerAccept = $this->apiClient->selectHeaderAccept([]);
		if (!is_null($headerAccept)) {
			$headerParams[HttpRequest::HEADER_KEY_ACCEPT] = $headerAccept;
		}
		$headerParams[HttpRequest::HEADER_KEY_CONTENT_TYPE] = $this->apiClient->selectHeaderContentType([]);

		// query params
		$queryParams = [];
		if (!is_null($space_id)) {
			$queryParams['spaceId'] = $this->apiClient->getSerializer()->toQueryValue($space_id);
		}
		if (!is_null($id)) {
			$queryParams['id'] = $this->apiClient->getSerializer()->toQueryValue($id);
		}
		if (!is_null($recipient_iban)) {
			$queryParams['recipientIban'] = $this->apiClient->getSerializer()->toQueryValue($recipient_iban);
		}
		if (!is_null($sender_iban)) {
			$queryParams['senderIban'] = $this->apiClient->getSerializer()->toQueryValue($sender_iban);
		}

		// path params
		$resourcePath = '/invoice-reimbursement-service/update-iban';
		// default format to json
		$resourcePath = str_replace('{format}', 'json', $resourcePath);

		// form params
		$formParams = [];
		
		// for model (json/xml)
		$httpBody = '';
		if (isset($tempBody)) {
			$httpBody = $tempBody; // $tempBody is the method argument, if present
		} elseif (!empty($formParams)) {
			$httpBody = $formParams; // for HTTP post (form)
		}
		// make the API Call
		try {
			$response = $this->apiClient->callApi(
				$resourcePath,
				'POST',
				$queryParams,
				$httpBody,
				$headerParams,
				null,
				'/invoice-reimbursement-service/update-iban'
            );
			return new ApiResponse($response->getStatusCode(), $response->getHeaders());
		} catch (ApiException $e) {
			switch ($e->getCode()) {
                case 409:
                    $data = ObjectSerializer::deserialize(
                        $e->getResponseBody(),
                        '\VRPayment\Sdk\Model\ClientError',
                        $e->getResponseHeaders()
                    );
                    $e->setResponseObject($data);
                break;
                case 442:
                    $data = ObjectSerializer::deserialize(
                        $e->getResponseBody(),
                        '\VRPayment\Sdk\Model\ClientError',
                        $e->getResponseHeaders()
                    );
                    $e->setResponseObject($data);
                break;
                case 542:
                    $data = ObjectSerializer::deserialize(
                        $e->getResponseBody(),
                        '\VRPayment\Sdk\Model\ServerError',
                        $e->getResponseHeaders()
                    );
                    $e->setResponseObject($data);
                break;
			}
			throw $e;
		}
	}


}
