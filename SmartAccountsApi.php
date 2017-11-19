<?php

class SmartAccountsApi {

	public function sendRequest( $body, $apiUrl, $extraParams = null ) {
		if ( $body == null ) {
			$body = new stdClass();
		}
		$now = new DateTime();
		$now->setTimezone( new DateTimeZone( 'Europe/Tallinn' ) );
		$pk = get_option( "sa_api_pk" );
		$sk = get_option( "sa_api_sk" );

		$bodyJson  = json_encode( $body );
		$ts        = $now->format( "dmYHis" );
		$urlParams = "apikey=$pk&timestamp=$ts";
		$urlParams = $extraParams == null ? $urlParams : $urlParams . "&$extraParams";

		$sig = hash_hmac( 'sha256', $urlParams . $bodyJson, $sk );
		$url = "https://sa.smartaccounts.eu/api/$apiUrl?$urlParams&signature=$sig";

		$ch = curl_init();

		curl_setopt( $ch, CURLOPT_URL, $url );
		curl_setopt( $ch, CURLOPT_POST, 1 );
		curl_setopt( $ch, CURLOPT_HTTPHEADER, [ 'Content-Type: application/json' ] );
		curl_setopt( $ch, CURLOPT_POSTFIELDS, $bodyJson );
		curl_setopt( $ch, CURLOPT_RETURNTRANSFER, true );
		$server_output = curl_exec( $ch );
		$responseCode  = curl_getinfo( $ch, CURLINFO_HTTP_CODE );
		curl_close( $ch );
		if ( $responseCode != 200 ) {
			throw new Exception( "SmartAccounts call failed: $responseCode. $server_output" );
		}

		$saResponse = json_decode( $server_output, true );

		return $saResponse;
	}
}