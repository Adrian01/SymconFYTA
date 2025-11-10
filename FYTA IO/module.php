<?php

declare(strict_types=1);
	class FYTAIO extends IPSModule
	{

		// -----------------------------------------------------------------------------
		// Definition der Datenpaket GUIDs
		// -----------------------------------------------------------------------------
		private const TX_TO_CHILD = '{DED6317A-9EAD-FCB6-8358-C31657346F3A}'; // Configurator → IO
		private const RX_FROM_CHILD   = '{2801D2B9-67C9-9459-A24B-0C5493D54D4D}'; // IO → Configurator 


		public function Create()
		{
			//Never delete this line!
			parent::Create();

			$this->RegisterPropertyString("username","");
			$this->RegisterPropertyString("password","");
			$this->RegisterAttributeString("AccessToken","");
			$this->RegisterAttributeInteger("TokenExpires", 0);
			
		}

		public function Destroy()
		{
			//Never delete this line!
			parent::Destroy();
		}

		public function ApplyChanges()
		{
			//Never delete this line!
			parent::ApplyChanges();

			if (IPS_GetKernelRunlevel() != KR_READY ) 
				{
					$this->SendDebug('FYTA Cloud-IO', 'System noch nicht bereit, Cloud Login noch nicht möglich!', 0);
					return;
				}

				$this->SendDebug('FYTA Cloud-IO', 'Modul bereit! Starte Authentifizierungsprozess', 0);
				$this->CheckTokenStatus();

		}
		

		// -------------------------------------------------------------------------------------------------------------------------------------------------------------
		// Empfangene Kommandos von Konfigurator und Device verarbeiten
		// -------------------------------------------------------------------------------------------------------------------------------------------------------------

		public function ForwardData($JSONString)
		{
			$data = json_decode($JSONString, true);
			$buffer = json_decode($data['Buffer'] ?? '{}', true);
			$command = $buffer['Command'] ?? '';
			$senderID = $buffer['SenderID'] ?? 0;

			$this->SendDebug('FYTA Cloud IO', 'Anfrage empfangen von ' . $senderID . ' ' . $command . '', 0);
			$result = null;

			switch ($command) 
			{

				// -----------------------------------------------------------------------------
				// Abfrage aller im Account verfügbaren Pflanzen
				// -----------------------------------------------------------------------------

				case 'GetPlants':
					$this->SendDebug('FYTA Cloud IO', 'Cloud Request: Sensoren (Pflanzen) werden vom Server abgerufen', 0);
					$result = $this->CloudRequest('user-plant', 'GET');
					break;

				// -----------------------------------------------------------------------------
				// Abfrage aller Details und Messwerte einer Pflanze
				// -----------------------------------------------------------------------------

				case 'GetPlantDetails':
					$plantID = $buffer['PlantID'] ?? 0;

					if ($plantID <= 0) 
						{
							$this->SendDebug('FYTA Cloud IO', 'Ungültige PlantID oder Sensor nicht mehr vorhanden', 0);
							return json_encode(['error' => 'invalid_plant_id']);
						}

						$this->SendDebug('FYTA Cloud IO', "Cloud Request: GET user-plant/$plantID", 0);
						$result = $this->CloudRequest('user-plant/' . $plantID, 'GET');
					break;


				// -----------------------------------------------------------------------------
				// Lädt FYTA- und Benutzerbild, wandelt in Base64 um und sendet ans Device
				// -----------------------------------------------------------------------------

				case 'GetPlantImage':
					$plantID = (int)($buffer['PlantID'] ?? 0);

					if ($plantID <= 0) 
						{
							$this->SendDebug('FYTA Cloud IO', 'Ungültige PlantID für GetPlantImage', 0);
							$result = ['error' => 'invalid_plant_id'];
							break;
						}

					$this->SendDebug('FYTA Cloud IO', "Cloud Request: GET user-plant/$plantID (Bildabruf)", 0);

					$details = $this->CloudRequest("user-plant/$plantID", 'GET');
					if (empty($details['plant'])) 
						{
							$this->SendDebug('FYTA Cloud IO', 'Keine Pflanzendaten für GetPlantImage erhalten', 0);
							$result = ['error' => 'no_plant_data'];
							break;
						}

					$plant = $details['plant'];
					$urls = ['origin' => $plant['plant_origin_path'] ?? '', 'custom' => $plant['origin_path'] ?? ''];

					$images = [];
					foreach ($urls as $type => $url) 
						{
							if (empty($url)) 
								{
									$this->SendDebug('FYTA Cloud IO', "Kein $type-Bild vorhanden", 0);
									continue;
								}

							$this->SendDebug('FYTA Cloud IO', "Bildabruf: $type -> $url", 0);
							$data = ($type === 'custom') ? $this->DownloadProtectedImage($url) : @file_get_contents($url);

							if ($data === false || strlen($data) < 100) 
								{
									$this->SendDebug('FYTA Cloud IO', "Fehler beim Laden des $type-Bildes", 0);
								} 
							else 
								{
									$this->SendDebug('FYTA Cloud IO', ucfirst($type) . "-Bild erfolgreich geladen (" . strlen($data) . " Bytes)", 0);
									$images[$type] = base64_encode($data);
								}
						}

					$result = [
								'plant' => 
									[
										'plant_origin_path'   => $urls['origin'],
										'origin_path'         => $urls['custom'],
										'origin_image_base64' => $images['origin'] ?? null,
										'custom_image_base64' => $images['custom'] ?? null
									]
							];
					break;
			}

			
			// -----------------------------------------------------------------------------
			// Cloud Rückgabeprüfung und Antwort an Children
			// -----------------------------------------------------------------------------

			if ($result === null) 
				{
					$this->SendDebug('FYTA Cloud IO', 'Kein Ergebnis erhalten', 0);
					return json_encode(['error' => 'no_result']);
				}

			$this->SendDataToChildren(json_encode(
				[

				'DataID'   => self::RX_FROM_CHILD,
				'TargetID' => $senderID,    
				'Buffer'   => json_encode(['Command' => $command, 'Result'  => $result])
				
				]));

				$this->SendDebug('FYTA Cloud IO', "Antwort an Device gesendet (Command: $command, TargetID: $senderID)", 0);
				return json_encode($result);

		}

		
		// -------------------------------------------------------------------------------------------------------------------------------------------------------------
		// Hilfsfunktionen
		// -------------------------------------------------------------------------------------------------------------------------------------------------------------
		
		// Prüfen ob ein Token vorhanden und gültig ist, wenn nein -> Neuer Token anfordern führt Funktion Authenticate() aus
		private function CheckTokenStatus(): bool
		{
			$token   = $this->ReadAttributeString('AccessToken');
			$expires = $this->ReadAttributeInteger('TokenExpires');
			$now     = time();

			// Kein Token vorhanden
			if (empty($token)) 
				{
					$this->SendDebug('FYTA Cloud IO', 'Kein Token vorhanden, starte Authentifizierungsvorgang', 0);
					return $this->Authenticate();
				}

			// Token abgelaufen
			if ($expires > 0 && $now >= $expires) 
				{
					$this->SendDebug('FYTA Cloud IO', 'Token ist abgelaufen, starte erneute Authentifizierung', 0);
					return $this->Authenticate();
				}

			// Token gültig
			$this->SendDebug('FYTA Cloud IO', 'Gültiger Token vorhanden, Modul aktiv', 0);
			$this->SetStatus(IS_ACTIVE);
			return true;
		}

		// Führt den Authentifizierungsprozess zur FYTA Cloud durch und speichert den Token
		private function Authenticate(): bool
		{
			$username = $this->ReadPropertyString('username');
			$password = $this->ReadPropertyString('password');

			if ($username === '' || $password === '') 
				{
					$this->SendDebug('FYTA Cloud IO', 'Login nicht möglich, Benutzername oder Passwort fehlt -> Instanzkonfiguration prüfen', 0);
					$this->SetStatus(IS_INACTIVE);
					return false;
				}

			$this->SendDebug('FYTA Cloud IO', 'Starte Authentifizierung bei FYTA Cloud', 0);

			$url = 'https://web.fyta.de/api/auth/login';
			$postData = json_encode(['email' => $username, 'password' => $password]);

			$ch = curl_init($url);
			curl_setopt_array($ch, 
				[
					CURLOPT_RETURNTRANSFER => true,
					CURLOPT_POST           => true,
					CURLOPT_POSTFIELDS     => $postData,
					CURLOPT_HTTPHEADER     => ['Content-Type: application/json'],
					CURLOPT_TIMEOUT        => 5
				]
			);

			$response = curl_exec($ch);
			$status   = curl_getinfo($ch, CURLINFO_HTTP_CODE);
			curl_close($ch);

			// Prüfen, ob Cloud erreichbar ist
			if (!$this->HandleHttpStatus($status, 'Login', $response)) 
				{
					$this->SendDebug('FYTA Cloud IO', 'Keine erfolgreiche Verbindung zur Cloud', 0);
					$this->SetStatus(200);
					return false;
				}

			// Antwort prüfen und Access Token speichern
			$tokenResponse = json_decode($response, true);

			if (!isset($tokenResponse['access_token'])) 
				{
					$this->SendDebug('FYTA Cloud IO', 'Antwort enthält kein Access Token', 0);
					$this->SetStatus(200);
					return false;
				}

			$this->WriteAttributeString('AccessToken', $tokenResponse['access_token']);
			$this->WriteAttributeInteger('TokenExpires', time() + (int)($tokenResponse['expires_in'] ?? 0));

			$this->SendDebug('FYTA Cloud IO', 'Authentifizierung erfolgreich, Token gespeichert', 0);
			$this->SetStatus(IS_ACTIVE);
			return true;
		}


		// Zentrale Funktion zur Datenabfrage bei der FYTA-Cloud API
		private function CloudRequest(string $endpoint, string $method = 'GET', ?array $body = null): ?array
		{
			$token = $this->ReadAttributeString('AccessToken');
			if ($token === '') 
				{
					$this->SendDebug('FYTA Cloud IO', 'Kein AccessToken vorhanden, bitte zuerst Authentifizieren!', 0);
					return null;
				}
			
			//Basis URL zur Datenabfrage, Endpunkt wird Dynamisch ergänzt durch Übergabe des Strings $endpoint in die Funktion
			$url = 'https://web.fyta.de/api/' . ltrim($endpoint, '/');
			$method = strtoupper($method);
			$this->SendDebug('FYTA Cloud IO', "$method $url", 0);
			
			//Aufbau des Headers
			$headers = ['Authorization: Bearer ' . $token, 'Content-Type: application/json'];
			

			//Datenabruf mit passendem Endpunkt und Header
			$ch = curl_init($url);
			curl_setopt_array($ch, 
			[
				CURLOPT_RETURNTRANSFER => true,
				CURLOPT_HTTPHEADER     => $headers,
				CURLOPT_TIMEOUT        => 15,
				CURLOPT_CUSTOMREQUEST  => $method
			]);

			if (in_array($method, ['POST', 'PUT', 'PATCH'], true) && $body !== null) 
				{
					curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($body));
				}

			$response = curl_exec($ch);
			$status   = curl_getinfo($ch, CURLINFO_HTTP_CODE);


			if ($response === false) 
				{
					$errorMsg = curl_error($ch);
					curl_close($ch);
					$this->SendDebug('FYTA Cloud IO', "cURL Fehler bei $method $url: $errorMsg", 0);
					$this->HandleHttpStatus($status, "CloudRequest($endpoint)", "cURL Fehler: $errorMsg");
					return null;
				}

			curl_close($ch);

			if (!$this->HandleHttpStatus($status, "CloudRequest($endpoint)", $response)) 
				{
					return null;
				}
			
			//Rückgabe des JSON aus der Cloud Antwort
			return json_decode($response, true);

		}

		// Zentrale Auswertung und Debug-Ausgabe für den HTTP-Statuscode
		private function HandleHttpStatus(int $status, string $context, string $response = ''): bool
		{
			switch ($status) 
				{
					case 200:
						$this->SendDebug('FYTA Cloud IO', "Cloud-Anfrage erfolgreich", 0);
						return true;

					case 204:
						$this->SendDebug('FYTA Cloud IO', "Keine Daten (204 No Content)", 0);
						return true;

					case 400:
						$this->SendDebug('FYTA Cloud IO', "Ungültige Anfrage (400 Bad Request)", 0);
						break;

					case 401:
						$this->SendDebug('FYTA Cloud IO', "Authentifizierung fehlgeschlagen, das angegeben Passwort stimmt nicht mit dem Benutzernamen überein!", 0);
						$this->SetStatus(200);
						break;

					case 403:
						$this->SendDebug('FYTA IO', "Zugriff verweigert (403 Forbidden)", 0);
						$this->SendDebug('FYTA Cloud IO', "Cloud Response: $response", 0);
						break;

					case 404:
						$this->SendDebug('FYTA Cloud IO', "Der angegebene Benutzername existiert nicht!", 0);
						break;

					case 408:
						$this->SendDebug('FYTA Cloud IO', "Anfrage-Timeout (408 Request Timeout)", 0);
						break;

					default:
						$this->SendDebug('FYTA Cloud IO', "[$context] Unerwarteter HTTP-Status: $status", 0);
						$this->SendDebug('FYTA Cloud IO', "Cloud Response: $response", 0);
						break;
				}

			
			return false;
		}

		// Abruf der Bilddatei (Pflanzenbild) von der FYTA-Cloud
		private function DownloadProtectedImage(string $url): ?string
		{
			$token = $this->ReadAttributeString('AccessToken');

			if ($token === '') 
				{
					$this->SendDebug('FYTA IO', 'Kein Access Token verfügbar, Download der Bilddatei abgebrochen', 0);
					return null;
				}

			$ch = curl_init($url);
			curl_setopt_array($ch, 
			[
				CURLOPT_RETURNTRANSFER => true,
				CURLOPT_FOLLOWLOCATION => true,
				CURLOPT_HTTPHEADER => ['Authorization: Bearer ' . $token, 'Accept: image/*', 'User-Agent: Symcon FYTA IO'],
				CURLOPT_TIMEOUT => 10
			]);

			$data = curl_exec($ch);
			$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
			curl_close($ch);

			if ($httpCode !== 200 || $data === false || strlen($data) < 100)
				{
					$this->SendDebug('FYTA IO', "Bildabruf fehlgeschlagen (HTTP $httpCode)", 0);
					return null;
				}

			$this->SendDebug('FYTA IO', "Bild erfolgreich von Cloud geladen ($httpCode, " . strlen($data) . " Bytes)", 0);
			return $data;

		}
	
	}