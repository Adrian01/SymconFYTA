# IP-Symcon FYTA 

[![Version](https://img.shields.io/badge/Symcon-PHPModul-red.svg)](https://www.symcon.de/service/dokumentation/entwicklerbereich/sdk-tools/sdk-php/)
[![Version](https://img.shields.io/badge/Modul%20Version-1.0-blue.svg)]()
[![Version](https://img.shields.io/badge/Symcon%20Version-7.0%20%3E-green.svg)](https://www.symcon.de/forum/threads/30857-IP-Symcon-5-3-%28Stable%29-Changelog)

![LOGO](docs/img/fyta.png?raw=true "logo")

**Inhaltsverzeichnis**

1. [Funktionsumfang](#1-funktionsumfang)
2. [Voraussetzungen](#2-voraussetungen)
3. [Unterstützte Gerätetypen](#3-unterstützte-gerätevarianten)
4. [Installation](#4-installation)
5. [Funktionsreferenz](#5-funktionsreferenz)
6. [Statusvariablen](#6-statusvariablen)
7. [Anhang](#7-anhang)
   1. [GUIDs der Module](#guids-der-module)
   2. [Spenden](#spenden)
  



## 1. Funktionsumfang

Das **FYTA Modul für IP-Symcon** ermöglicht die Integration der **FYTA Beam Pflanzensensoren** und deren Pflanzendaten aus der **FYTA Cloud** in IP-Symcon.
Damit lassen sich sämtliche Sensorwerte, Zustände und Pflanzendetails bequem auswerten und in der Hausautomatisierung weiterverwenden.


### Aktuelle Features

- Automatische Synchronisation aller FYTA-Sensoren und Pflanzen über den Cloud-Account  
- Anlage einzelner FYTA Device-Instanzen für jede Pflanze
- Abruf und Darstellung aller relevanten Messwerte (Feuchte, Licht, Salz, Temperatur, pH)  
- Anzeige und Auswertung folgender Sensorwerte:
	- Temperatur
	- Bodenfeuchte
	- Lichtintensität
	- Salzgehalt
	- pH-Wert (manueller Wert aus der APP)

- Anzeige des allgemeinen Pflanzenzustands (Plant Health)
- Anzeige der letzten Messwertübertragung des Sensors
- Anzeige von Batterie- und Sensorstatus
- Speicherung von Pflanzenbildern:
	- Originalbild (FYTA Cloud)
	- Benutzerdefiniertes App-Bild (eigene Aufnahme)
- Lokale Ablage der Bilder im Symcon-Medienverzeichnis
- Automatische, zeitgesteuerte Aktualisierung über das eingestellte Intervalle


## 2. Voraussetzungen

- **IP-Symcon Version:** 7.0 oder höher  
- **FYTA Cloud Account** mit verknüpften FYTA-Sensoren und Pflanzen  
- **Internetverbindung** für die Kommunikation mit der FYTA-Cloud API  


## 3. Unterstützte Gerätevarianten

| Typ             | Unterstützt        |
| --------------- | ------------------ |
| FYTA Hub        | :white_check_mark: |
| FYTA Beam Gen 1 | :white_check_mark: |


## 4. Installation

### 4.1 Einbindung in IP-Symcon

Damit das Modul **FYTA** verwendet werden kann, muss dieses über den **Module Store** installiert werden. 
Hierfür muss der **Module Store** geöffnet werden. Dieser befindet sich im oberen rechten Bereich. 
Über das Suchfeld kann via "FYTA" das Modul **FYTA** gefunden werden. 
Beim öffnen des gefundenen Modules, kann im folgenden Dialog die Installation des Moduls via "Installieren"-Knopf angestoßen werden. 

![Store](docs/img/search_module_store.png?raw=true "search module")

Modul installieren...

![Store](docs/img/install_module_store.png?raw=true "install module")


Im Anschluss öffnet sich der Hinzufügendialog zum Erstellen einer **FYTA Konfigurator** Instanz.

Soll die Instanz manuell hinzugefügt werden muss eine Instanz vom **FYTA Konfigurator** erstellt werden. Dazu muss zuerst der Objektbaum geöffnet werden. In diesem den Hinzufügen-Button "+" unten rechts betätigen und Instanz auswählen.

![Store](docs/img/add_instance.png?raw=true "add_instance")

Mithilfe der Schnellsuche kann der **FYTA Konfigurator** vom Hersteller "(FYTA)" gefunden werden. 
Der Ort sollte nicht verändert werden, der Name kann beliebig gewählt werden. Abschließend mit "OK" bestätigen.

![Store](docs/img/search_instance.png?raw=true "search_instance")

Nach der Erstellung der Konfigurator-Instanz öffnet sich diese automatisch und kann eingerichtet werden.
Bevor der Konfigurator verwendet werden kann, muss im nachfolgenden Dialog **Schnittelle konfigurieren** der Login in der automatisch erzeugten Instanz **FYTA Cloud-IO** durchgeführt werden.

Trage deine Zugangsdaten für dein FYTA Konto ein und bestätige mit "OK".

![Store](docs/img/configure_instance.png?raw=true "configure_instance")


Jetzt öffnet sich der Konfigurator und es können alle erkannten Geräte mit einem Klick auf "Erstellen" erstellt werden.

![Store](docs/img/configurator.png?raw=true "configurator")


## 5. Funktionsreferenz

Das Modul stellt derzeit keine externen PHP-Funktionsaufrufe bereit.
Alle Daten werden automatisch über die FYTA Cloud synchronisiert.


## 6. Statusvariablen

|           Variable          |   Typ   | Beschreibung                                                                                                                        |
| :-------------------------: | :-----: | :---------------------------------------------------------------------------------------------------------------------------------- |
|       **Pflanzenname**      |  String | Name der Pflanze, wie sie vom Benutzer in der FYTA App vergeben wurde                                                               |
| **Wissenschaftlicher Name** |  String | Botanischer bzw. wissenschaftlicher Name der Pflanzenart                                                                            |
|     **Pflanzenzustand**     | Integer | Allgemeiner Zustand der Pflanze unter Berücksichtigung aller Messwerte (z. B. Temperatur, Feuchtigkeit, Licht, Salzgehalt, pH-Wert) |
|      **Sensorzustand**      | Integer | Status des Sensors (0 = kein Sensor, 1 = Online, 2 = Offline > 1,5 h keine Daten)                                                   |
|  **Letzte Aktualisierung**  |  String | Zeitpunkt, zu dem der Sensor zuletzt Messwerte an die FYTA Cloud übermittelt hat                                                    |
|         **Batterie**        | Integer | Aktueller Batterieladestand des Sensors in Prozent                                                                                  |
|        **Temperatur**       |  Float  | Aktuelle Umgebungstemperatur am Standort der Pflanze (gemessen durch den Sensor)                                                    |
|    **Zustand Temperatur**   | Integer | Bewertung der Temperatur im Verhältnis zum optimalen Bereich für die Pflanze                                                        |
|       **Bodenfeuchte**      |  Float  | Gemessene Bodenfeuchtigkeit in Prozent                                                                                              |
|   **Zustand Bodenfeuchte**  | Integer | Bewertung der Feuchtigkeit im Verhältnis zum idealen Bereich für die Pflanze                                                        |
|     **Lichtintensität**     |  Float  | Aktuell gemessene Lichtintensität am Standort (µmol/s)                                                                              |
|      **Zustand Licht**      | Integer | Bewertung der Lichtverhältnisse im Verhältnis zum Pflanzenbedarf                                                                    |
|        **Salzgehalt**       |  Float  | Aktueller Leitwert (Salzgehalt) des Bodens in mS/cm                                                                                 |
|    **Zustand Salzgehalt**   | Integer | Bewertung des Salzgehalts im Verhältnis zum idealen Bereich für die Pflanze                                                         |
|         **pH-Wert**         |  Float  | Vom Benutzer in der FYTA App manuell eingetragener pH-Wert des Bodens (nicht vom Sensor gemessen)                                   |
|     **Zustand pH-Wert**     | Integer | Bewertung des angegebenen pH-Werts im Verhältnis zum optimalen Bereich                                                              |
|      **Letzte Düngung**     |  String | Vom Benutzer in der FYTA App eingetragenes Datum der letzten Düngung                                                                |
|     **Nächste Düngung**     |  String | Berechneter Zeitpunkt oder Empfehlung für die nächste Düngung basierend auf der letzten Eintragung                                  |

**Hinweise**

Alle Variablen mit „Zustand …“ enthalten bewertete Zustände anhand der FYTA-Referenzwerte:
1 = zu niedrig, 2 = niedrig, 3 = optimal, 4 = hoch, 5 = zu hoch.

Die Rohwerte (Temperatur, Feuchtigkeit, Licht, Salzgehalt) stammen direkt vom FYTA Sensor.

pH-Wert und Letzte Düngung werden vom Benutzer in der FYTA App manuell eingetragen.

Die Statusvariablen werden automatisch aktualisiert, sobald neue Messwerte von der FYTA Cloud empfangen werden.

Der Sensorstatus wird automatisch auf „Offline“ gesetzt, wenn länger als 1,5 Stunden keine neuen Daten eintreffen.


## 7. Anhang

###  GUIDs der Module

|         Modul         |      Typ     |                   GUID                   |
| :-------------------: | :----------: | :--------------------------------------: |
|   **FYTA Cloud-IO**   |      IO      | `{B7DFBFD1-BC13-2E7E-F7D3-1B00A6B315A5}` |
| **FYTA Konfigurator** | Configurator | `{BA08E64E-FAFF-EA4A-BA4B-09314800FA1A}` |
|    **FYTA Device**    |    Device    | `{2D06563E-2506-1A55-90F0-DBE3C24B86D8}` |


###  Spenden

Dieses Modul ist für die nicht kommzerielle Nutzung kostenlos, Schenkungen als Unterstützung für den Autor werden hier akzeptiert:    

<a href="https://www.paypal.com/cgi-bin/webscr?cmd=_s-xclick&hosted_button_id=H35258DZU36AW" target="_blank"><img src="https://www.paypalobjects.com/de_DE/DE/i/btn/btn_donate_LG.gif" border="0" /></a>