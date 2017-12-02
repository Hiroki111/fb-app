<?php

namespace App\Http\Controllers;

use App\User;
use Cookie;
use Facebook\Facebook;
use GuzzleHttp\Client;
use Illuminate\Http\Request;

class HomeController extends Controller
{
    protected $request;
    /**
     * Create a new controller instance.
     *
     * @return void
     */
    public function __construct(Request $request, Client $restClient, User $user)
    {
        $this->request    = $request;
        $this->restClient = $restClient;
        $this->user       = $user;
        $this->fb         = new Facebook([
            'app_id'                => env('FACEBOOK_APP_ID'),
            'app_secret'            => env('FACEBOOK_APP_SECRET'),
            'default_graph_version' => env('FACEBOOK_DEFAULT_GRAPH_VERSION'),
        ]);
    }

    public function index()
    {
        if (!$this->request->cookie('fb_access_token')) {
            return view('login');
        }

        $url    = "https://graph.facebook.com/me?access_token=" . $this->request->cookie('fb_access_token');
        $result = $this->restClient->request('GET', $url);
        $user   = $this->user
            ->where('is_active', 1)
            ->where('access_token', $this->request->cookie('fb_access_token'))
            ->first();

        if ($result->getStatusCode() === 200 && !empty($user)) {
            $response = $this->fb->get('/me?fields=picture.width(300)', $user->access_token);

            return view('index', [
                'id'      => $user->id,
                'name'    => $user->name,
                'picture' => $response->getGraphObject()->asArray('picture')['picture']['url'],
            ]);
        }

        return view('login');
    }

    public function login()
    {
        return redirect(env('FACEBOOK_OAUTH_URL') . '?client_id=' . env('FACEBOOK_APP_ID') . '&redirect_uri=' . env('APP_URL') . '/success');
    }

    public function loginSuccess()
    {
        $urlShortToken    = "https://graph.facebook.com/v2.11/oauth/access_token?client_id=" . env('FACEBOOK_APP_ID') . "&redirect_uri=" . env('APP_URL') . "success&client_secret=" . env('FACEBOOK_APP_SECRET') . "&code=" . $this->request->input('code');
        $resultShortToken = $this->restClient->request('GET', $urlShortToken);
        $shortAccessToken = json_decode($resultShortToken->getBody()->getContents())->access_token;

        $urlToken    = "https://graph.facebook.com/v2.11/oauth/access_token?grant_type=fb_exchange_token&client_id=" . env('FACEBOOK_APP_ID') . "&client_secret=" . env('FACEBOOK_APP_SECRET') . "&fb_exchange_token=" . $shortAccessToken;
        $resultToken = $this->restClient->request('GET', $urlToken);
        $accessToken = json_decode($resultToken->getBody()->getContents())->access_token;

        $urlForUser    = "https://graph.facebook.com/me?access_token=" . $accessToken;
        $resultForUser = $this->restClient->request('GET', $urlForUser);
        $userInfo      = json_decode($resultForUser->getBody()->getContents());

        $user = $this->user->firstOrCreate([
            'name'  => $userInfo->name,
            'fb_id' => $userInfo->id,
        ]);
        $user->access_token = $accessToken;
        $user->is_active    = 1;
        $user->save();

        return redirect('/')->cookie(
            'fb_access_token', $accessToken
        );
    }

    public function logout($id)
    {
        $user            = $this->user->find($id);
        $user->is_active = 0;
        $user->save();
        $this->fb->delete('/me/permissions', ['access_token' => $user->access_token]);
        return redirect('/')->withCookie(Cookie::forget('fb_access_token'));
    }
}
