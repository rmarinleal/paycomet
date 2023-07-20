<?php

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\Routing\Annotation\Route;
use \Httpful\Request as HttpfulRequest;


/**
 * Class defaultController
 * @package App\Controller\Form
 */
class DefaultController extends AbstractController
{
    # Jet Id of the sandbox account used on this exercise
    const JETID = '8HDAtu5xWUpfFZKMIzGv0dE72kOPciVJ';

    /**
     * Index page, will resolve the main domain root url and show the payment form
     * 
     * @param Request $request
     *
     * @return Response
     *
     * @throws BadRequestHttpException
     */
    #[Route('/', name: 'index')]
    public function index(Request $request) : Response {
        $fields = $request->query->all();
        $params = ['jetId' => self::JETID];

        # Show an error box on the front if the parameter errorMessage is sent on the url.
        if (isset($fields['errorMessage']) && !empty($fields['errorMessage'])) {
            $params['errorMessage'] = $fields['errorMessage'];
        }
        return $this->render(
            'index.html.twig', $params
        );
    }
    
     /**
     * OK return url, will show a success message to the consumer.
     * 
     * @param Request $request
     *
     * @return Response
     *
     * @throws BadRequestHttpException
     */
    #[Route('/url-ok', name: 'urlOk')]
    public function getOkRequest(Request $request) : Response {
        try {
            $fields = $request->query->all();
            return $this->render(
                'ok.html.twig',['orderId' => $fields['r']]
            );
        } catch (\Exception $exception) {
            throw new BadRequestHttpException('Get error exception ' . $exception->getMessage());
        }
        
    }
        
     /**
     * KO return url, will show an error message to the consumer.
     * 
     * @param Request $request
     *
     * @return Response
     *
     * @throws BadRequestHttpException
     */
    #[Route('/url-ko', name: 'urlKo')]
    public function getKoRequest(Request $request) : Response {
        try {
            $fields = $request->query->all();
            return $this->render(
                'ko.html.twig',[]
            );
        } catch (\Exception $exception) {
            throw new BadRequestHttpException('Get error exception ' . $exception->getMessage());
        }
    }

    /**
     * Execute the payment process by creating an user card and using it to crate a payment
     * 
     * @param Request $request
     *
     * @return Response
     *
     * @throws BadRequestHttpException
     */
    #[Route('/processPayment', methods: ['POST'])]
    public function postRequestAction(Request $request)
    {
        try {
            date_default_timezone_set("Europe/Madrid");
            $fields = $request->request->all();
            $token  = $fields["paytpvToken"];

            if ($token && strlen($token) == 64) {
                $endPoint       = "https://rest.paycomet.com/v1/cards";
                $merchantCode   = "mpcahy7a";
                $terminal       = "62302";
                $password       = "5rWyLGC31RMJvS4EzoIi";
                $jetID          = "8HDAtu5xWUpfFZKMIzGv0dE72kOPciVJ";
                $apiKey         = "4c52f86eace19b5acdc0369e54f5123ef966b4f2";
                $signature      = sha1($merchantCode.$token.$jetID.$terminal.$password);
                $ip             = $_SERVER["REMOTE_ADDR"];
                $merchantOrder  = "order".rand(1, 9999);

                # Launch a POST request to v1/cards to create an user
                # HTTPFul library used to ease the cURL request. https://github.com/nategood/httpful
                $paycometUser = HttpfulRequest::post($endPoint)
                    ->addHeaders([
                        'Accept' => 'application/json',
                        'Content-Type' => 'application/json',
                        'PAYCOMET-API-TOKEN' => $apiKey,

                    ])
                    ->body(json_encode([
                        'terminal'          => $terminal,
                        'jetToken'          => $token,
                        'order'             => $merchantOrder,
                        'cardHolderName'    => $fields["username"],
                    ]))
                    ->send();

                $paycometUserResponse = json_decode($paycometUser->raw_body, true);

                # Return to the payment form with an error message if something fails
                if (!isset($paycometUserResponse['idUser']) || !isset($paycometUserResponse['tokenUser'])) {
                    return $this->redirect('http://localhost:8080?errorMessage=Error, unable to create user card');
                }

                # With the user card created, launch the payment 
                $endPoint  = "https://rest.paycomet.com/v1/payments";
                $idUser    = $paycometUserResponse['idUser'];
                $tokenUser = $paycometUserResponse['tokenUser'];

                $paycometPayment = HttpfulRequest::post($endPoint)
                    ->addHeaders([
                        'Accept' => 'application/json',
                        'Content-Type' => 'application/json',
                        'PAYCOMET-API-TOKEN' => $apiKey,

                    ])
                    ->body(json_encode([
                        'payment' => [
                            'terminal'           => $terminal,
                            'order'              => $merchantOrder,
                            'amount'             => $fields["amount"],
                            'currency'           => 'EUR',
                            'methodId'           => '1',
                            'originalIp'         => $ip,
                            'secure'             => '1',
                            'idUser'             => $idUser,
                            'tokenUser'          => $tokenUser,
                            'productDescription' => 'test product description',
                            'merchantData'       => [],
                        ]
                    ]))
                    ->send();
                $paycometPaymentResponse = json_decode($paycometPayment->raw_body, true);
                
                # Return to the payment form with an error message if something fails
                if(isset($paycometPaymentResponse['errorCode']) && !empty($paycometPaymentResponse['errorCode'])) {
                    $url = 'http://localhost:8080?errorMessage=Error code ' . $paycometPaymentResponse['errorCode'];
                    if (isset($paycometPaymentResponse['error']) && isset($paycometPaymentResponse['error']['message'])) {
                        $url .= ' ' . $paycometPaymentResponse['error']['message'];
                    }
                    return $this->redirect($url);
                }

                # if all goes right, redirect to the paycomet challenge screen to complete the secure validation process 
                return $this->redirect($paycometPaymentResponse['challengeUrl']);

            } else {
                return $this->redirect('http://localhost:8080?errorMessage=Error, unable to get a token');
            }
        } catch (\Exception $exception) {
            throw new BadRequestHttpException('Post error exception ' . $exception->getMessage());
        }
    }
}