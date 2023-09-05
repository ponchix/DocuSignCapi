<?php

namespace App\Http\Controllers;

use DocuSign\eSign\Client\ApiClient;
use DocuSign\eSign\Configuration;
use GuzzleHttp\Client;
use Illuminate\Http\Request;

class TestController extends Controller
{
    //
    public function index()
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

            $url = "https://account-d.docusign.com/oauth/auth?";

            $botUrl = $url . $queryBuild;
            // dd($botUrl);
            return redirect()->to($botUrl);
        } catch (Exception $e) {
            return redirect()->back()->with('error', 'Something Went wrong !');
        }
    }

    public function redirectToDocusign()
    {
        $config = new Configuration();
        $config->setHost(env('DOCUSIGN_BASE_PATH'));
        print_r($config->set);
        $config->setClientId(env('DOCUSIGN_CLIENT_ID'));
        $config->setClientSecret(env('DOCUSIGN_CLIENT_SECRET'));
        $config->setIntegratorKey(env('DOCUSIGN_ACCOUNT_ID'));
        $config->setRedirectUri(env('DOCUSIGN_BASE_URL'));

        $apiClient = new ApiClient($config);
        $authUri = $apiClient->getAuthorizationUri();

        return redirect($authUri);
    }

    public function handleDocusignCallback(Request $request)
    {
        $code = $request->query('code');
        // Handle the code and authenticate the user with DocuSign
    }
}
