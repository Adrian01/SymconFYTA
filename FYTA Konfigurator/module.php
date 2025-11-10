<?php

declare(strict_types=1);
    class FYTAKonfigurator extends IPSModule
    {
        // -------------------------------------------------------------------------------------------------------------------------------------------------------------
        // Definition der Datenpaket und Modul GUIDs
        // -------------------------------------------------------------------------------------------------------------------------------------------------------------
        private const RX_FROM_PARENT    = '{2801D2B9-67C9-9459-A24B-0C5493D54D4D}';
        private const TX_TO_PARENT      = '{DED6317A-9EAD-FCB6-8358-C31657346F3A}';
        private const FYTA_IO_GUID      = '{B7DFBFD1-BC13-2E7E-F7D3-1B00A6B315A5}';
        private const FYTA_DEVICE_GUID  = '{2D06563E-2506-1A55-90F0-DBE3C24B86D8}';

        public function Create()
        {
            parent::Create();

            //Cloud IO 
            $this->RequireParent(self::FYTA_IO_GUID);
        

            $this->RegisterAttributeString('LastPlants', '[]');
            
        }

        public function ApplyChanges()
        {
            parent::ApplyChanges();

            //Datenfilter setzen, Konfigurator empfängt nur Daten vom Cloud IO, die auch für ihn bestimmt sind.
            $this->SetReceiveDataFilter('.*"TargetID":' . $this->InstanceID . '.*');
        }


        // -------------------------------------------------------------------------------------------------------------------------------------------------------------
        // Empfangene Daten vom Cloud IO verarbeiten
        // -------------------------------------------------------------------------------------------------------------------------------------------------------------
        
        public function ReceiveData($JSONString)
        {
            $data   = json_decode($JSONString, true);
            $buffer = json_decode($data['Buffer'] ?? '{}', true);
            $this->SendDebug('FYTA Cloud IO', 'Cloud Response: ' . json_encode($buffer) . '',0 );

            if (($buffer['Command'] ?? '') !== 'GetPlants') 
                {
                    return;
                }

            $result = $buffer['Result'] ?? [];
            if (!isset($result['plants']) || !is_array($result['plants'])) 
                {
                    $this->SendDebug('FYTA Cloud IO', 'Cloud Response: ERROR' . json_encode($buffer) . '',0 );
                    return;
                }

            $this->WriteAttributeString('LastPlants', json_encode($result['plants']));
            $this->ReloadForm();
        }


        // -------------------------------------------------------------------------------------------------------------------------------------------------------------
        // Konfigurator Formular aufbauen
        // -------------------------------------------------------------------------------------------------------------------------------------------------------------
        
        public function GetConfigurationForm()
        {
            $plants = json_decode($this->ReadAttributeString('LastPlants'), true) ?: [];

            // Beim Öffnen automatisch aktualisieren, wenn Cache leer
            if (empty($plants)) 
                {
                    $this->GetPlants();
                }

            $values = [];
            foreach ($plants as $plant) 
                {
                    $nickname   = $plant['nickname'] ?? '-';
                    $sensorID   = $plant['sensor']['id'] ?? '';
                    $plantID    = $plant['id'] ?? 0;
                    $received   = $plant['sensor']['received_data_at'] ?? '-';
                    $hubID      = $plant['hub']['hub_name'] ?? '-';
                    $instanceID = $this->FindInstanceBySensorID($sensorID);

                    // Sensorstatus (0 = Kein, 1 = Online, 2 = Offline)
                    $statusValue = $plant['sensor']['status'] ?? 0;
                    $sensorStatus = match ($statusValue) 
                        {
                            0 => 'Kein Sensor',
                            1 => 'Online',
                            2 => 'Offline',
                            default => 'Unbekannt'
                        };

                    // Hubstatus (1 = Online, 2 = Offline)
                    $hubStatusValue = $plant['sensor']['status'] ?? 0;
                    $hubStatus = match ($hubStatusValue) 
                        {
                            1 => 'Online',
                            2 => 'Offline',
                            default => 'Unbekannt'
                        };

                    $values[] = 
                        [
                            'instanceID'       => $instanceID,
                            'nickname'         => $nickname,
                            'sensor_id'        => $sensorID,
                            'received_data_at' => $received,
                            'hub_name'         => $hubID,
                            'hub_status'       => $hubStatus,
                            'sensor_status'    => $sensorStatus,
                            'create' => [
                                'moduleID' => self::FYTA_DEVICE_GUID,
                                'name'     => $nickname,
                                'configuration' => 
                                [
                                    'PlantID'   => $plantID,
                                    'SensorID'  => $sensorID,
                                    'Nickname'  => $nickname
                                ]

                            ]
                        ];
                }

            $actions = 
                [
                    [
                        "type" => "Image",
                        "image" => "data:image/webp;base64,UklGRqYiAABXRUJQVlA4WAoAAAA4AAAA2wUAtgEASUNDUKgBAAAAAAGobGNtcwIQAABtbnRyUkdCIFhZWiAH3AABABkAAwApADlhY3NwQVBQTAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAA9tYAAQAAAADTLWxjbXMAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAlkZXNjAAAA8AAAAF9jcHJ0AAABTAAAAAx3dHB0AAABWAAAABRyWFlaAAABbAAAABRnWFlaAAABgAAAABRiWFlaAAABlAAAABRyVFJDAAABDAAAAEBnVFJDAAABDAAAAEBiVFJDAAABDAAAAEBkZXNjAAAAAAAAAAVjMmNpAAAAAAAAAAAAAAAAY3VydgAAAAAAAAAaAAAAywHJA2MFkghrC/YQPxVRGzQh8SmQMhg7kkYFUXdd7WtwegWJsZp8rGm/fdPD6TD//3RleHQAAAAAQ0MwAFhZWiAAAAAAAAD21gABAAAAANMtWFlaIAAAAAAAAG+iAAA49QAAA5BYWVogAAAAAAAAYpkAALeFAAAY2lhZWiAAAAAAAAAkoAAAD4QAALbPQUxQSJEZAAAB8FbbVh5t27ZNCUsCEpCAhEhAQiTgIBKQEAlIiAQkLAnrvL6OY9+rJgTWVT8iYgJsYXbBBxZj3vF+mzG/wVHU1u6DORQ1yhUTvDIOfGRnZuX1JGOugcRlizdNQjbOKjun4TMTNQ1vp1Mr4Bht8VZM4kPK8s4JH4KbmdWXU4x5B8m2eFQmIRjrZ+MUfGpQZpZeTVBqiUS2xXtiEi9aFrZNl49Bofa8mtuYV3AUXTwPZrHzurbNgQ/uzOx8McmYq5C4bPGmWTiMt+6ahk9O1FTeS6d2gmO0xVsxizcxOzZN+CjczKy+ltOYPyDZFo/KLIgxv/dMwWcHZWbppYhSSySyLd4Ts3hSM9kxXT4MhVp7KdWYV3AUXTwPprFzO3fMgY9/mFl+JcmYq5C4bPGmaYjGvW+Yhs9P1FTeyEPtBMdoi7diGis5i/slfAEqM7teSDbmD0i2xaMyDaLs6nYp+EZRZhZfhyi1RCLb4j0xjdnYq2yWLl+Bk1p7HZcxv8BRdPE8mMdGz/JmOfClDzPLLyMacxUSly3eNA/B+Le90vCtiVqXd9GoZXCMtngr5rEMwMJWCV+DyszKq8jGvIFkWzwqE9FHcO2Ugu8VZWbhRYhSiySyLd4T83jYCPtG6fJFOKm1F1GM+QWOoovnwUTWIdixTw589cPMjtcQjLkKicsWb5oIsTHWbdLw3ZFafw2NWgbHaIu3YiLPQZjskvBluJhZeQmHMW8g2RaPykw8ozg3ScG3izLT8AqkU4sksi3eExMZbZTPHunydcjM7H4FxZhf4Ci6eB7MZB2GxS1ygGBjZukFBGPehcRlizdNhY6j7pAGhpFafwE3tQMcoy3eipnMNk7dIYECLmZWpi8Z8waSbfGoTEUbiOX9UcBRlJnK7HVqgUS2xXtiJoONtG2PLiSQmVmdvGLMCziKLp4HU1mGYmF3HKDZmFmauqDMupC4bPGmuehjKZujgWeg9kxdNeYHOEZbvBVTmWysfXMEIijM7Jy4ZMwbSLbFozIXdTB2bI0CptKZqczbQy2QyLZ4T0yl2GjrzuhCBQczq9N2GvMCjqKL58Fc5uGYbIwDZBszi5MmyqyD5GWLN03GM568LxrYBmpt0qoxTySiLd6KuYw23mdfBDoozCxPWTLmN0i2xaMyGdeALO6KAr7SmanMWGOmgUS2xXtiMnVE16boQggHM7smLBvzAo6ii+fBZGYbsW6KA5QbMwvTJcqsg+RlizfNxj0ky1uigXNQZm26LmOeSERbvBWTGWzM95YIpFCY2TFZ0ZjfINkWj8psnIOysCEKaHdmXeaqMdNAItviPTGbfVRlP3ThlZhZmapszAs4ii6eB7OZbNR9PxwgfjOzMFHSmT0gedniTdNRh2VpNzQwD8qsTVQx5olEtMVbMZui46q7IVBDYWZpmoIxryDZFo/KdGQbt8peKCDfmfVpasxUSGRbvCem8xmY5a3QhV1iZmWSDmN+gqP0xfNgOoON/NkKB+jfzDTMUWf2gGSxxZvm4xqahY3QwD8oMbunqBjzRCLY4q2YTx3btRHCAHAyszRBQZlVkGyLR2U+Dhu77oOCIT7M+gTdRlyFxGGL98R83oOzYxd0GUNiZuf0JGN+gqP0xfNgPsVGf++CA4OszFRmpzN7QLLY4k0Tcg7Pwh5oGKUoMauTU4x5JBFs8VZMaB/fuQfCMHAyszQ1oswukGyLR2VCoo2/b4GCgT7MnqmpRlyFxGGLt2BC6wRY3ABdRpKYWZ6YZMwzOEpfPB0TKjoDdQMcGGplpjIvD7MGksUWb5qRbDOosvwaxipKzK5pOY15JBFs8d6Y0TYFlpdfGAwyM4uTIsrsAsm2eDTMSLA5bKuvYLiNWZuUasRVSBy2eAtm9JoEC2uvy3giM8tTEo15Bkfpi6djSvssXGvvwIAvZioz0pg1kCy2eNOUHDaLfek1jFiUmJUJycY8kgi2eG9MaZ0GO1ZeGBIyMwvTIcqsgGRbPBqmRGwe74VXMOjGrE3HZcS7kDhs8RZM6TkRJsuuy6giMzsmIxrzAyT74umY02cmzpeClXkx6zIXjVnDKy1kCt56tJl89psoMStTcRjz4BnqVFjcbjiYWZgI6cwKPIPORd1vaMzuiShGvItnyDaXuuECM0vTEIz5Ac/QJsPyfkNh1qehMWvwDMFms2046cSsTEIy4hpcQ5kOC/sNBzMNc9CZFbiGPh9lw6ERs3sKihHvcA3J5rPvuMDM0gQEZZZ8Q50QOzYcCrNnAm4jfsM1iM1o3XHSidk5vGTENfiGPCUmGw4HM5XRdWYFvuGZk7zjcBOzOrjTiHf4hmhz+my5oMQsDU2UWXIO16RY3HEozNrQqhG/4Rx0Vq4th07M8sCSEdfgHLLNqu65xExlXA+zE87hnhbLWw43MbuGdRrxB84h2Lzeey4oMYuDEmWWvMM5MRa2HAqzNqjLiFd4hz4zZc+hE7M8pGjEVbxDMq5Kpm+6xKzLiBqzE96hkjk7F0t7DpWYlQFlI/7AO4iSCYVM3XSixCwMR5RZcg/ZuD4IZEz2HE5mbTjFiFe4h4fMCTxk8qbDQ8yOwQQjruIegpENwEnm2XWJWZexNGYn3MNF5gYQyFjYdKjErAzlMOIN/kHJZAB4yFy7TpSYhoFIZxb9w2Fk5X+cZHTX4SRm90CKEb/gH24yN/6nkLFj1+EhZmkYwYir+IdgZPP/gpvMve0Ssz6Mm1mGfzjJqPxvmYyFXYeLmJVBJCPe4CA6mYr/Xdic206UmMoYOrPoIKKRPf4P3GT6tkMmZnUIxYhfcBCVjOL/zmQsbTs0YpYGEJSYioMQJVP/AZRM3XeR2TOA24gfcBDZyB7/pJJR2Xa4iNlJLxnxBg/RyHT804OM5X0nSkyF3cMseIhgZK9/BCXT9h0yMavkTiNe4CEuNvGfVTIW9h0aMYvURIl1cRGdTMc/T2yujReZNWrViB/wEIeRvf4FOpm+8XARs0wsGfEGF3Gzif/mImPHxpNOTIXXwyy4CDGyD/5tZHNvPBzE7KKVjXiBizjZnP8KnYzJxkMjZpGUKLEuPuJhE/7dxebceYFZI3UZ8QMuIhrZB/8+sOk7D4WYHZSiEb/hIyqb8w/gIWNx50kn1oVRI6bBSSib8CdONnXn4SBmhVA24gU+IhvZG38ysNGth0bMAh3pxDqcRGOT/wgeMpa3XmDW6BQjnpxEMLbyZ042beuhELNEJhjxG06isLnxZ4WNha0nnVgn04hp8BKdTf5DuNmUrYdEzAqVw4gXOInDyKr8qcym7z3cxDQQkU6sw0tUNhV/WtjYsfeC8rKbSDHiyUuIsT3+GG42de+hELNEIyixG14is1H8+czGZO+hE+s0buOt4iYeNvUvQNmcmy8Rs5NEMuInvEQ0tsffqGyezYebmAqHTuyBm7jYKP7mwcbi5gvKyyqFYsSTn1A211+Bsrk2HwoxSwSCEqtwE9nYxr9T2ejuw0PsIVCNt4qfuNl0/N3ExvLuS8Ts/LpkxE+4iWBsr7+EzubefajEVL7tIfbATxQ68W9dbCzsPlFedn3ZacSTo+hsOv52pFN2H05iFr9KlNgFP5GM7fnX0Nn07YeHWPuqarxVHEWlE/7excbS9kvELH9RNOIZfkKUzYO/H+nU7YdKTOV7GrEGR5GN7fkBeNiYbD9RXnZ9TTbi0VM8dMInnHTy9sNJzMKXiBK74CiCsW34xEDn2X94iLUvuYy3iqe46OSPwMPGwv6LxOz4imjEMzyF0pHPOOlc+w8XsS7f0Ig1eIrD2N74zEBHHYAoLytfcBjx6CpuOvlDcLOxY/8hE7PwcdKJXfAUwejKp2Q6twNAI3Z/XDHeXVzFSafiU4WOBQcQiVn6sGDED7iKTuf4GNx0TgeAi1j/sEaswVVEY6v43EynewBRXlY+6jDiwVdUOvWDRNlYcgDIxDR8UidW4CpE6RwfhEqnegA0XnZ/UDHeXXxFNraKTz7oqHiASMzSxwQldsBXNDrXR0HZWPYAKMSej7mNd4OvCEY3flal01yAdF52fkgy4sFZXHQ6PvugY8ED4CCm8hmdWIGz6HSuD0Onc7kANF5WP+I03l2cxWF046dddLoPCMQsfYAosQRncdPp+PRIxw4XgELs+YBqvG84CzG658eh07l9gHRelv9aMt4avMXJJ3zeRcfEBeAgpvK3HmIF3qLTefD5kc/pA9B42fWXTuPd4S2i0T2/AA+d7gSC8rL4V0SJJXdR+YRvOOlY9AEoxNpfuYz3DXehdBq+MfCpTgCdl+W/EI23BneRjW7+Cjx0VJxAItblzzViBe6i8ZHvOOlYdgK4eVn5Y9l4P3AXweje+M7Ap3mBoLws/CFRYslfFD75S3DTseAEUIi1P3QZ7wp/0fnIt2Q+xQug87LjjwTjreIvDqN741uFT3cDiViXP9GInfAXlc/xNbjp2OEFcPOy8gcO4/3AX4jRVXxv5lPdQFBeGv6VdGLJYZx86heJ0jHxAjh52f2vivGucBgPn+OLUPmcbgAPL0v/IhhvFYcRja7imw8+jx9IxPq/aMROOIyLT/0qKB2LbgCVl5V/lIz3A4+hfOJ3VT7VD4jy0vBPOrHoMbLR7fjug4/6AZy8rP6DYrwveIybz/VlUDqW/QAeXpb+j6C8VDxGML7x2y4+tyNIxJ7/4zbeGR6j8On49sjHgh9A5WXn/5KMd4PL6HzK16HzKY5AlJfK/3iIRZeRjG/4votPdwQ4eVkFcBrvCy6j8nnw/ZGPJUeAxssSRHmpuAwxvicBdD7VE0RiDdV4Z7iMTCgwOPmYOAJcvKwa7waf8fB5wDAQyp5AlBfz6DOC8c0U8PB5PAHyhBX4jIuQcDj5WPQEaNPVxWkonxscA6HLFcTpOuAzDuObSaDxUVeAa7IanMZNSFhkPna4AtG5Ck4jGN8bLIXQ7QpwTFWB0zgJHTRw87EwR226y6yhTVQXr9H5KHhmQucczXectjBRB5xGMr6ViBDqS+rCvJdpavAaldBBBJWPpQWlMnHSJ0mD1xDlo2B6EKoLKmPmj0kq8BrZ+FYqUD4qy6lh7tsUdbiNRihyqXwsL6c4eWGKktsIxreD60HoWU0XZr9M0A23cRG6yED5WFhLKtMnfXo0+I1OKLK5CF1rKWP+j+kpcBuH8e1gGwn1pdTwBu/J6fAbN6FCB52PHSspvoKgc5P8hhjhwOcidC+kC++wTM0Nv3ESesA3EjJZRiovAX1iNDiOTugkhE7oXEYZbzFNzAm/EY1wYHQS6quo4T3e0/LAcVRCDxgHQhYXUXwRQWcleQ4llCnhIVTX0IU3WSalwnFkIyycTkIqK0jlVaBPiYrnaIRucA6ELK+gjHeZpuSE4whGOJNCI9QWUMPbvCfkgecojIRVJmRh/cTXITofyXV0QjdYC6OyfC68z3M6KjzHYYQzLdyE+upReSF4JkPFdVRCCt6ZkB2LJ+ONpsk44TnECFdiwqiunYZ3WqeiwXWcjA5iuAmZLJ34UkRnIvqOh5CC+cHoXDkX3uo5ERdcRzTClRqU0LNwVF4LnmlQ8R0Xo8StErK4bjLea5qGDN+hhDq4H4zqsml4s9ckNPiObIQvclBCumziqxGdg+g8bkaRXSVkedFceLd5Ci74jmCEO9hHRm3NqLwctAlQcR6F0UUPnZCFJZPxduMEHHAenVHgdzEqK6bh/V7Da3AeyQg/4B8Z9RUTX5Do6IL3qIzOAaATsrReLrzhPLgC5yHGOIygMKrLReUVoQ2ti/fIjB6MMDAyWS0Z7zgO7YD3eBidQ8DDKC+Whrd8DazBe0RjLGM4GT2LJb4m6eMK7uNidGOMgZHFpXLhPR/DKnAfyigPAg+ja6WovCi0QXVxH4cxllFkRrpSMt50GNQB93EzujFKYWR5nTS86zKkG+4jGOM8DNyM7nUSX5b0AWnwHycjxTgzIwur5MLbPgZU4D86ozoQoXQuEpXXhTacDv+RjPExENyM+iLJeN9hOMmBVEaKkR6MLC2RhjdeBnPDf4gyqkOBMqpLJL4y6UPR4ECyMU5jqYxUFsiFd56GUuBAHkYdYz0YWV4fKi8N90A6HEgwxtdgoIye9ZHx1oOOI3mQi1IcTWVkYXU0vPcyjBsepDPqGG2kdK2O+OLQB6HiQQ5jfKfhKiNdHBfefBrECQ9yU3qJx9JQeXW4h/DAg4ity3tpZLz7oCNILuRcGCYLo+HtlwFUuJC+Ms6FEV8fHnoqLiTayuzr4sL7T/ROuJC6NCyuCpUFgErugQsRXRt1VWSsQFFuyYdkW5sqa+LBGjypVfiQtjgsr4m0CPAQU/EhwVZnWxIVqzARy/AhZXlYWBAqywCVVoMT6evjWhAn1qEoq+hEDluffT08WIknqQtOpC4QOyYiTWJYCkicxYmIrdA6Efh/mW/uXCImv1qeNXL+aIm2Rp8fLXWRWPzNoquk/mTJtkr1J8u9TCz/YAm2TtsPlrJQLPxe6Sul/FxJtlL7z5W6VCz9WBFbq/XHSl4sJr9VntWSf6pEW63PT5VruVj8paLr5fqhcth61R8q94Kx/DMl2Iq9f6acS8bCr5S+Zs4fKcnWbP+RUheNpZ8ooqum/kTJtmpVfqE8y8byD5Rg6/b5gXItHAu/T/rKuX6eHLZy9efJvXTs+HEitnbvHyfn4rHw26SvnvOnSbTV23+a1OVj8YeJ6PqpP0yyrV+V3yVtAVn+WRJsBbefJdcSsvCrpK+h60fJYWu4/yipi8iOnyRiq/j+SXIuI5NfJM86On+QRFvHzw+SupAs/h7RlVR/jmRbyfpzpC0lyz9Ggq3l5iokpXSWUs6UUnAqhVRvE0zKgo8IubRu/7a3K0d30kkdmOCbVPEP4bzV/mI7oydJxrljhg9S3TmE67G/36/oRiqpMkXonOxwDJIf+9R+igsRIx3mqJCqbiFcah9dowPJpBrmOJAy8Qmh2ue35D4eUnmScJPKHkGqfecdfEc0zopZzqQeh1DUvvYSz3GRuqYJysmiN4iPfXM/HIeSCvN0kbqcQbFvv8VrZOP8YJ4DKXUF4bHv78lp3KTyRKFxsuwIDjWKp8sIxlllpjKp2w8UY1nFYZykKqZaOVlwAlKN5yP+opOKc1VJFR8gjzF9grdIxvnBXEdS3QXIY1w1OotKKk8WHk6WHIA8xlajqxAlJbOVSdX9J4/x1eApsnGumG1RTirbrxnjRxzFQypNFyony7uvGufHTwTj3DHfkdSz+U5jXd3EReqcMHROFrZeMt6nl1BSMmMnqWvniRKz6CMO41wx40JKd14z5l1cxE3qmDJUTnbsu9O4Xx5CjHPHnCdS97YLSs6SgzhJlUlD52Rh1zVj38U/dFJh1gqpc9Mdxr+4h2icb8x6INU3XR+ABu9QSR3ThpuTxS1XbITVOYhy6pj3g1TdcaJDsOAbsnG+Jg6dk8qGKzbG6hsaqTBzhZPl/SY6CAueIRjnhpkPpNp+yzbK6hkuUnnq0DhZ2G59GCqOoXNSzH0mde22ZOPMfuEwztfkQTn13VYH8viFSirO3sXJjs2mA7HgFcQ4P5j9QOrea4eN9PQKJ6k8fWicTLZaHcrjFR5OKvOXSZ1bTYdiwSdE41wx/6Kc+k6LNtbsEyqp+AJQOVncaGUw1ScopwdvMJKqG+0eTHcJ2TifrwAPJ91oOhgTj9BIyTvInCxvs2CjTQ4hGOeKdyjKqW2zYzjFIRRS6SWgcrKwy8pwbofQOXW8xUSq7LI6nOYPDuN8vgZ0Tn2XteGoP6ik5D2cnOzYZM9wzB2Icb7xHoVU3WQ23uANMqnjRaByMnEqyRs8nDreZCJ1/sSIxrm8CnROzxYT93ORCu+icLK4w5L7UU433mUgdf3AyMb5eBm4OekPjJuT4m0enCxvMDifYJyv14HO6f55UUiF93FxsvDronNqeJ+BVPEo4gmScc4vBI1T32BtPPAElZPijWZOlvxJ9wSinK5XAuVU99c1nOYJsnGO7+TiZLK9zuFcnuDh9OCdBlJ5e6XhnI4gGOf8UvBweraXDCc5gouTylvJnCzsLvTRwBEop4q3Ksrp2l51MI8jOIxzfC2onHR75cFcjuDm1PFeIyc7dlcYzOEHgnE+XwweTvfuQh+L+IGTlLyZzMnC7rqGcsMPdE4Vb1ZInbsrDiX7gWic06tB5dR3F/pIxA9UTh3vNnGytLvKQG64AVFO58tB51R3VxjI4QeycQ5v5+SksrlwD6PDDzRON96ucLK8u9IwTj8QjPPxelA5td2FPggVP3Bx6ni/BycLuysPosAPdE7lBaFzunYX+hBU/MBhnMMbKpz69kpDKPADN6cbbzhwsmN3oQ2gix8Q45xfEW5O9/aKAzjgB05Oind8cDLZXbjoNTiCh9P1kqCczu0lnZwGRxCNc3hLF6e+vZDInXAElVPDWw6cLG4vXNRueALllF8TGqe6v/AQU/EE2Sgr3nPmpBssKK8IT9A41RcFpWR5fyHSyvAEwTjHN3VxahsMmdQFV1A4PXjTkZOFDYZCqcIXdE75VeHhVHYYKqEbvuAwyirvKnPqWwyVziPOoHKqeNeilOzYYqhkboEvEOMcXxYqp7rHUKhULOfpy5w63nbkZNjkmciFH+RRSWjGT/LwUOgRv8ovArfgd3nqX6YHfprL9VW34Nd5bF/TE36hp/4VPeNXen4+rmf8Uk/3R7WMX+uh9A/RK+Ine7z6X+v1wA/3kGv/Y/0+I36/SzpLa/+otaskwf8PBwBWUDggfAYAAPCfAJ0BKtwFtwE+RSKPRaKiIRBvvAAoBES0t3C7/wAM7Fg/4B+AGmA/AD9qv7XyAXYDs/aA/gH4Ab3/nIH9VygD9AOD/AF6wxdTfRtQ5xlAPdTfRtQ5xlAPdTfRtQ5xlAPdTfRtQ5xlAPdTfRtQ5xlAPdTfRtQ5xlAPdTfRtQ5xlAPdTfRtQ5xlAPdTfRtQ5xlAPdTfRtQ5xlAPdTfRtQ5xlAPdTfRtQ5xlAPdTfRtQ5xlAPdTfRtQ5xlAPdTfRtQ5xlAPdTfRtQ5xlAPdTfRtQ5xlAPdTfRtQ5xlAPdTfRtQ5xlAPdTfRtQ5xlAPdTfRtQ5xlAPdTfRtQ5xlAPdTfRtQ5xlAPdTfRtQ5xlAPdTfRtQ5xlAPdTfRtQ5xlAPdTfRtQ5xlAPdTfRtQ5xlAPdTfRtQ5xlAPdTfRtQ5xlAPdTfRtQ5xlAPdTfRtQ5xlAPdTfRtQ5xlAPdTfRtQ5xlAPdTfRtQ5xlAPdTfRtQ5xlAPdTfRtQ5xlAPdTfRtQ5xlAPdTfRtQ5xlAPdTfRtQ5xlAPdTfRtQ5xlAPdTfRtQ5xlAPdTfRtQ5xlAPdTfRtQ5xlAPdTfRtQ5xlAPdTfRtQ5xlAPdTfRtQ5xlAPdTfRtQ5xlAPdTfRtQ5xlAPdTfRtQ5xlAPdTfRtQ5xlAPdTfRtQ5xlAPdTfRtQ5xlAPdTfRtQ5xlAPdTfRtQ5xlAPdTfRtQ5xlAPdTfRtQ5xlAPdTfRtQ5xlAPdTfRtQ5xlAPdTfRtQ5xlAPdTfRtQ5xlAPdTfRtQ5xlAPdTfRtQ5xlAPdTfRtQ5xlAPdTfRtQ5xlAPdTfRtQ5xlAPdTfRtQ5xlAPdTfRtQ5xlAPdTfRtQ5xlAPdTfRtQ5xlAPdTfRtQ5xlAPdTfRtQ5xlAPdTfRtQ5xlAPdTfRtQ5xlAPdTfRtQ5xlAPdTfRtQ5xlAPdTfRtQ5xlAPdTfRtQ5xlAPdTfRtQ5xlAPdTfRtQ5xlAPdTfRtQ5xlAPdTfRtQ5xlAPdTfRtQ5xlAPdTfRtQ5xlAPdTfRtQ5xlAPdTfRtQ5xlAPdTfRtQ5xlAPdTfRtQ5xlAPdTfRtQ5xlAPdTfRtQ5xlAPdTfRtQ5xlAPdTfRtQ5xlAPdTfRtQ5xlAPdTfRtQ5xlAPdTfRtQ5xlAPdTfRtQ5xlAPdTfRtQ5xlAPdTfRtQ5xlAPdTfRtQ5xlAPdTfRtQ5xlAPdTe8KRH6q4c1MtrhzUy2uHNTLa4c1MtrhzUy2uHNTLa4c1MtrhzUy2oj5YB7qb6NqHOMoB7qb6NqHOMoB7qb6NqHOMoB7gFIj+Cp6m+jahzjKAe6lrEfLAPdTfRtQ5xlAPdTfRtQ5xlAPdTfRtQ5xlAPcCnR9G1DnGUA91N9GxHszahzjKAe6m+jahzjKAe6m+jahzjKAe6m+jahzgGI+WAe6m+jahzjKAaJPbEYupvo2oc4ygHupvo2oc4ygHupvo2oc4ygHumHszahzjKAe6m+jahtsR8sA91N9G1DnGUA91N9G1DnGUA91N9G1DnGUA9wEeZRmaWoc4ygHupvo2ocwSe2IxdTfRtQ5xlAPdTfRtQ5xlAPdTfRtQ5xlAPdMGx3nuI+WAe6m+jahzjJ/p7M2oc4ygHupvo2oc4ygHupvo2oc4ygHupvo2obZ1a3CY4c1LARi6YSaJMTi2uHNTLa4c1MtrhzUy2uHNTLa4c1Mtrhz1hi6m+jahzjKAe6m+jahzi4AA/v9f4AAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAFR/8LWG7+9fZ/6Hp//i+f/4tw//8Vt3d7EdfS30zwbuEHOSmkelNT3lNT3lNT3lNT3lNT3lNT3lNT3lNT3lNT3lNT3lNT3lNT3lNT3lNT3lNT3lNT3lNT3lNT3lNT3lNT3lNREAAcf/sRw7v719n/oEH3dX04NfNkBZwAAAAAAAJL/UEa3d2rUnXz6+EAAAAGNwGAAAuH2r6AAABDxglreHAAAAADH3WT1/gAABuftj/9iPf//gIjAq9am9Ot2K1PP+dX0ZHH0hjFLPVkd4cWgvT5wVCEAoA357//AV3/zcf/2oOuA7cf4KwcIpexWsHLGVMKiwP/5nDkaYLZf/M6kwfuLIoE4KEp8IE4KEp8IE4KEp8IE4KEp8IE4KEp8IE4KEp8IE4KEp8IE4KEoiAAAABFWElGugAAAEV4aWYAAElJKgAIAAAABgASAQMAAQAAAAEAAAAaAQUAAQAAAFYAAAAbAQUAAQAAAF4AAAAoAQMAAQAAAAIAAAATAgMAAQAAAAEAAABphwQAAQAAAGYAAAAAAAAALxkBAOgDAAAvGQEA6AMAAAYAAJAHAAQAAAAwMjEwAZEHAAQAAAABAgMAAKAHAAQAAAAwMTAwAaADAAEAAAD//wAAAqAEAAEAAADcBQAAA6AEAAEAAAC3AQAAAAAAAA==",
                        "width" => "250px",
                        "height" => "250px"
                    ],

                    [
                        'type'              => 'Configurator',
                        'name'              => 'PlantConfigurator',
                        'delete'            => true,
                        'columns' => [
                            ['caption' => 'Pflanzenname', 'name' => 'nickname', 'width' => '350'],
                            ['caption' => 'Sensor-ID', 'name' => 'sensor_id', 'width' => '250px'],
                            ['caption' => 'Status Sensor', 'name' => 'sensor_status', 'width' => '200px'],
                            ['caption' => 'Zuletzt aktualisiert', 'name' => 'received_data_at', 'width' => '200px'],
                            ['caption' => 'Hub', 'name' => 'hub_name', 'width' => '350'],
                            ['caption' => 'Status Hub', 'name' => 'hub_status', 'width' => 'auto'],
                        ],
                        'sort' => ['column' => 'nickname', 'direction' => 'ascending'],
                        'values' => $values
                    ]
                ];

            // Formular zurückgeben
            return json_encode(
                [
                    'actions'  => $actions
                ]);
        }

        // -------------------------------------------------------------------------------------------------------------------------------------------------------------
        // Abfrage der verfügbaren Pflanzen und Sensoren vom Cloud IO
        // -------------------------------------------------------------------------------------------------------------------------------------------------------------

        private function GetPlants()
        {
            $payload = 
            [
                'DataID' => self::TX_TO_PARENT,
                'Buffer' => json_encode(['Command' => 'GetPlants', 'SenderID' => $this->InstanceID])
            ];

            $this->SendDebug('FYTA Konfigurator', 'Request Cloud IO: Command [GetPlants]' . json_encode($payload) . '' ,0 );
            $this->SendDataToParent(json_encode($payload));
        }


        // -------------------------------------------------------------------------------------------------------------------------------------------------------------
        // Prüft ob der Sensor bereits als Geräteinstanz existiert
        // -------------------------------------------------------------------------------------------------------------------------------------------------------------

        private function FindInstanceBySensorID(string $sensorID): int
        {
            if ($sensorID === '') 
                {
                    return 0;
                }

            foreach (IPS_GetInstanceListByModuleID(self::FYTA_DEVICE_GUID) as $id)
                {
                    $cfg = json_decode(IPS_GetConfiguration($id), true);
                    if (($cfg['SensorID'] ?? '') === $sensorID) 
                        {
                        return $id;
                        }
                }
                return 0;
        }
    }

