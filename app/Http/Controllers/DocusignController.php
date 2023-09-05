<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use DocuSign\eSign\Configuration;
use DocuSign\eSign\Api\EnvelopesApi;
use DocuSign\eSign\Api\EnvelopesApi\ListTemplatesOptions;
use DocuSign\eSign\Client\ApiClient;
use Exception;
use GuzzleHttp\Client;
use Illuminate\Contracts\Session\Session as SessionSession;
use Session;

class DocusignController extends Controller
{

    /** hold config value */
    private $config;

    private $signer_client_id = 1000; # Used to indicate that the signer will use embedded
    private $base_url;
    private $account_id;
    private $version;
    /** Specific template arguments */
    private $args;

    public function __construct()
    {
        $this->base_url = env('DOCUSIGN_BASE_URL');
        $this->account_id = env('DOCUSIGN_ACCOUNT_ID');
        $this->version = env('DOCUSIGN_VERSION');
    }

    /**
     * Show the html page
     *
     * @return render
     */
    public function index()
    {
        return view('docusign.connect');
    }


    /**
     * Connect your application to docusign
     *
     * @return url
     */
    public function connectDocusign()
    {
        try {
            $params = [
                'response_type' => 'code',
                'scope' => 'signature',
                'client_id' => env('DOCUSIGN_CLIENT_ID'),
                'state' => 'a39fh23hnf23',
                'redirect_uri' => route('docusign.callback'),
            ];
            $queryBuild = http_build_query($params);
            // dd(json_encode($params));

            $url = "https://account-d.docusign.com/oauth/auth?";

            $botUrl = $url . $queryBuild;
            return redirect()->to($botUrl);
        } catch (Exception $e) {
            return redirect()->back()->with('error', 'Something Went wrong !');
        }
    }

    /**
     * This function called when you auth your application with docusign
     *
     * @return url
     */
    public function callback(Request $request)
    {
        $code = $request->code;

        $client_id = env('DOCUSIGN_CLIENT_ID');
        $client_secret = env('DOCUSIGN_CLIENT_SECRET');

        $integrator_and_secret_key = "Basic " . utf8_decode(base64_encode("{$client_id}:{$client_secret}"));

        $ch = curl_init();

        curl_setopt($ch, CURLOPT_URL, 'https://account-d.docusign.com/oauth/token');
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($ch, CURLOPT_POST, 1);
        $post = array(
            'grant_type' => 'authorization_code',
            'code' => $code,
        );
        curl_setopt($ch, CURLOPT_POSTFIELDS, $post);

        $headers = array();
        $headers[] = 'Cache-Control: no-cache';
        $headers[] = "authorization: $integrator_and_secret_key";
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);

        $result = curl_exec($ch);
        if (curl_errno($ch)) {
            echo 'Error:' . curl_error($ch);
        }
        curl_close($ch);
        $decodedData = json_decode($result);
        $request->session()->put('docusign_auth_code', $decodedData->access_token);

        return redirect()->route('docusign')->with('success', 'Docusign Succesfully Connected');
    }

    public function signDocument()
    {
        try {
            $this->args = $this->getTemplateArgs();

            $args = $this->args;

            // dd($args);

            $envelope_args = $args["envelope_args"];
            // dd($envelope_args);
            # Create the envelope request object
            $envelope_definition = $this->make_envelope($args["envelope_args"]);
            // dd($envelope_definition);  
            $envelope_api = $this->getEnvelopeApi();
            # Call Envelopes::create API method
            # Exceptions will be caught by the calling function

            $api_client = new \DocuSign\eSign\client\ApiClient($this->config);
            $envelope_api = new \DocuSign\eSign\Api\EnvelopesApi($api_client);
            $results = $envelope_api->createEnvelope($args['account_id'], $envelope_definition);
            $envelope_id = $results->getEnvelopeId();

            $authentication_method = 'authenticationInstant'; # How is this application authenticating
            # the signer? See the `authenticationMethod' definition
            # https://developers.docusign.com/esign-rest-api/reference/Envelopes/EnvelopeViews/createRecipient
            $recipient_view_request = new \DocuSign\eSign\Model\RecipientViewRequest([
                'authentication_method' => $authentication_method,
                'client_user_id' => $envelope_args['signer_client_id'],
                'recipient_id' => '1',
                'return_url' => $envelope_args['ds_return_url'],
                'user_name' => 'Alfonso Ramirez', 'email' => 'aramirez@capi.lat'
            ]);

            $results = $envelope_api->createRecipientView($args['account_id'], $envelope_id, $recipient_view_request);
            return redirect()->to($results['url']);
        } catch (Exception $e) {
            dd($e);
        }
    }


    private function make_envelope($args)
    {

        $filename = 'DocFirma.pdf';

        $demo_docs_path = asset('doc/' . $filename);

        $arrContextOptions = array(
            "ssl" => array(
                "verify_peer" => false,
                "verify_peer_name" => false,
            ),
        );

        $content_bytes = file_get_contents($demo_docs_path, false, stream_context_create($arrContextOptions));
        dd($content_bytes);
        $base64_file_content = base64_encode($content_bytes);
        dd($base64_file_content);
        # Create the document model
        $document = new \DocuSign\eSign\Model\Document([ # create the DocuSign document object
            'document_base64' => $base64_file_content,
            'name' => 'Example document', # can be different from actual file name
            'file_extension' => 'pdf', # many different document types are accepted
            'document_id' => 1, # a label used to reference the doc
        ]);
        # Create the signer recipient model
        $signer = new \DocuSign\eSign\Model\Signer([ # The signer
            'email' => 'aramirez@capi.lat', 'name' => 'Alfonso Ramirez',
            'recipient_id' => "1", 'routing_order' => "1",
            # Setting the client_user_id marks the signer as embedded
            'client_user_id' => $args['signer_client_id'],
        ]);
        # Create a sign_here tab (field on the document)
        $sign_here = new \DocuSign\eSign\Model\SignHere([ # DocuSign SignHere field/tab
            'anchor_string' => '/sn1/', 'anchor_units' => 'pixels',
            'anchor_y_offset' => '10', 'anchor_x_offset' => '20',
        ]);
        # Add the tabs model (including the sign_here tab) to the signer
        # The Tabs object wants arrays of the different field/tab types
        $signer->settabs(new \DocuSign\eSign\Model\Tabs(['sign_here_tabs' => [$sign_here]]));
        # Next, create the top level envelope definition and populate it.

        $envelope_definition = new \DocuSign\eSign\Model\EnvelopeDefinition([
            'email_subject' => "Please sign this document sent from the CodeHunger",
            'documents' => [$document],
            # The Recipients object wants arrays for each recipient type
            'recipients' => new \DocuSign\eSign\Model\Recipients(['signers' => [$signer]]),
            'status' => "sent", # requests that the envelope be created and sent.
        ]);

        return $envelope_definition;
    }

    /**
     * Getter for the EnvelopesApi
     */
    public function getEnvelopeApi(): EnvelopesApi
    {
        $this->config = new Configuration();
        $this->config->setHost($this->args['base_path']);
        $this->config->addDefaultHeader('Authorization', 'Bearer ' . $this->args['ds_access_token']);
        $this->apiClient = new ApiClient($this->config);

        return new EnvelopesApi($this->apiClient);
    }

    /**
     * Get specific template arguments
     *
     * @return array
     */
    private function getTemplateArgs()
    {
        $envelope_args = [
            'signer_client_id' => $this->signer_client_id,
            'ds_return_url' => route('docusign')
        ];
        $args = [
            'account_id' => env('DOCUSIGN_ACCOUNT_ID'),
            'base_path' => env('DOCUSIGN_BASE_URL'),
            'ds_access_token' => Session::get('docusign_auth_code'),
            'envelope_args' => $envelope_args
        ];

        return $args;
    }


    public function getTemplates()
    {
        $base_url = env('DOCUSIGN_BASE_URL');
        $account_id = env('DOCUSIGN_ACCOUNT_ID');
        $client = new Client([

            'base_uri' => $base_url . 'v2.1/accounts/' . $account_id . '/',
            'headers' => [
                'Accept'     => '*/*',
                'Authorization' => 'Bearer ' . Session::get('docusign_auth_code'),
                'Content-Type' => 'aplication/json',
                'Connection' => 'keep-alive',
                'Accept-Encoding' => 'gzip,deflate,bf'
            ]
        ]);
        try {
            $response = $client->request('GET', 'templates');
            $statusCode = $response->getStatusCode();

            $body = $response->getBody()->getContents();
            $data = json_decode($body);
            foreach ($data->envelopeTemplates as $value) {
                echo $value->name;
                echo $value->templateId;
            }
        } catch (Exception $e) {
            echo "Error: " . $e->getMessage() . PHP_EOL;
        }
    }

    public function sendEnvelopeTemplate()
    {
        $jsonData = [
            "emailBlurb" => "Create an envelope with a templateId",
            "emailSubject" => "Template",
            "compositeTemplates" => [
                [
                    "serverTemplates" => [
                        [
                            "sequence" => "1",
                            "templateId" => "c3d40da7-178a-416f-9ab1-eda483a50708"
                        ]
                    ],
                    "inlineTemplates" => [
                        [
                            "recipients" => [
                                "signers" => [
                                    [
                                        "email" => "aramirez@capi.lat",
                                        "name" => "Alfonso Ramirez",
                                        "recipientId" => "2",
                                        "roleName" => "seller",
                                        "tabs" => [
                                            "signHereTabs" => [
                                                [
                                                    "anchorString" => "test",
                                                    "anchorUnits" => "pixels",
                                                    "anchorXOffset" => "200",
                                                    "anchorYOffset" => "10",
                                                    "yPosition" => "150",
                                                    "xPosition" => "150"
                                                ]
                                            ]
                                        ]
                                    ]
                                ]
                            ],
                            "sequence" => "2"
                        ]
                    ]
                ]
            ],
            "status" => "sent"
        ];

        $jsonString = json_encode($jsonData);

        $client = new Client([

            'base_uri' => $this->base_url . $this->version . '/accounts/' . $this->account_id . '/',
            'headers' => [
                'Accept'     => '*/*',
                'Authorization' => 'Bearer ' . Session::get('docusign_auth_code'),
                'Content-Type' => 'application/json',
                'Connection' => 'keep-alive',
                'Accept-Encoding' => 'gzip,deflate,bf'
            ]
        ]);
        try {
            $response = $client->request('POST', 'envelopes', [
                'headers' => [
                    'Accept'     => '*/*',
                    'Authorization' => 'Bearer ' . Session::get('docusign_auth_code'),
                    'Content-Type' => 'application/json',
                    'Connection' => 'keep-alive',
                    'Accept-Encoding' => 'gzip,deflate,bf'
                ],
                'body' => $jsonString
            ]);
            $statusCode = $response->getStatusCode();

            $body = $response->getBody()->getContents();
            dd($body);
        } catch (Exception $e) {
            echo "Error: " . $e->getMessage() . PHP_EOL;
        }
    }
}
