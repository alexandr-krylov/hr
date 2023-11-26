<?php

namespace NW\WebService\References\Operations\Notification;

class TsReturnOperation extends ReferencesOperation
{
    public const TYPE_NEW    = 1;
    public const TYPE_CHANGE = 2;

    /**
     * @throws \Exception
     */
    public function doOperation(): array    //не верный тип возвращаемого значения
    {
        $data = (array)$this->getRequest('data');
        // а вдруг такого ключа нет или он не цифровой???
        $resellerId = is_numeric($data['resellerId']) ? (int)$data['resellerId'] : null;
        //$notificationType = (int)$data['notificationType'];// всегда будет целым есть оно или нет
        $notificationType = is_numeric($data['notificationType']) ? (int)$data['notificationType'] : null;
        $result = [
            'notificationEmployeeByEmail' => false,
            'notificationClientByEmail'   => false,
            'notificationClientBySms'     => [
                'isSent'  => false,
                'message' => '',
            ],
        ];

        //if (empty((int)$resellerId)) {  //всегда false
        if (is_null($resellerId)) {
            $result['notificationClientBySms']['message'] = 'Empty resellerId';
            return $result;
        }

        if (is_null($notificationType)) {
            throw new \Exception('Empty notificationType', 400);
        }

        $reseller = Seller::getById($resellerId);
        if ($reseller === null) {
            throw new \Exception('Seller not found!', 400);
        }
        // Если вдруг $data['clientId'] не существует
        $clientId = is_numeric($data['clientId']) ? (int)$data['clientId'] : null;
        if (is_null($clientId)) {
            throw new \Exception('Empty clientId!', 400);
        }
        $client = Contractor::getById($clientId);
        if ($client === null || $client->type !== Contractor::TYPE_CUSTOMER || $client->Seller->id !== $resellerId) {
            throw new \Exception('Client not found!', 400);
        }

        $cFullName = $client->getFullName();
        if (empty($client->getFullName())) {    // empty не годится для пробельной строки
            $cFullName = $client->name;
        }

        if (is_numeric($data['creatorId'])) {
            $cr = Employee::getById((int)$data['creatorId']);
        } else {
            throw new \Exception('Empty creatorId!', 400);
        }
        if ($cr === null) {
            throw new \Exception('Creator not found!', 400);
        }

        if (is_numeric($data['expertId'])) {
            $et = Employee::getById((int)$data['expertId']);
        } else {
            throw new \Exception('Empty expertId', 400);
        }
        if ($et === null) {
            throw new \Exception('Expert not found!', 400);
        }

        $differences = '';
        if ($notificationType === self::TYPE_NEW) {
            $differences = __('NewPositionAdded', null, $resellerId);
        } elseif ($notificationType === self::TYPE_CHANGE && !empty($data['differences'])) {
            $differences = __('PositionStatusHasChanged', [
                    'FROM' => Status::getName((int)$data['differences']['from']),
                    'TO'   => Status::getName((int)$data['differences']['to']),
                ], $resellerId);
        }
//(int)null будет 0. не подходит для проверки на empty(). 
        $templateData = [
            'COMPLAINT_ID'       => is_numeric($data['complaintId']) ? (int)$data['complaintId'] : null,
            'COMPLAINT_NUMBER'   => (string)$data['complaintNumber'],
            'CREATOR_ID'         => is_numeric($data['creatorId']) ? (int)$data['creatorId'] : null,
            'CREATOR_NAME'       => $cr->getFullName(),
            'EXPERT_ID'          => is_numeric($data['expertId']) ? (int)$data['expertId'] : null,
            'EXPERT_NAME'        => $et->getFullName(),
            'CLIENT_ID'          => (int)$data['clientId'],
            'CLIENT_NAME'        => $cFullName,
            'CONSUMPTION_ID'     => is_numeric($data['consumptionId']) ? (int)$data['consumptionId'] : null,
            'CONSUMPTION_NUMBER' => (string)$data['consumptionNumber'],
            'AGREEMENT_NUMBER'   => (string)$data['agreementNumber'],
            'DATE'               => (string)$data['date'],
            'DIFFERENCES'        => $differences,
        ];

        // Если хоть одна переменная для шаблона не задана, то не отправляем уведомления
        foreach ($templateData as $key => $tempData) {
            if (empty($tempData)) {
                throw new \Exception("Template Data ({$key}) is empty!", 500);
            }
        }

        $emailFrom = getResellerEmailFrom($resellerId);
        // Получаем email сотрудников из настроек
        $emails = getEmailsByPermit($resellerId, 'tsGoodsReturn');
        if (!empty($emailFrom) && count($emails) > 0) {
            foreach ($emails as $email) {
                MessagesClient::sendMessage([
                    0 => [ // MessageTypes::EMAIL
                           'emailFrom' => $emailFrom,
                           'emailTo'   => $email,
                           'subject'   => __('complaintEmployeeEmailSubject', $templateData, $resellerId),
                           'message'   => __('complaintEmployeeEmailBody', $templateData, $resellerId),
                    ],
                ], $resellerId, NotificationEvents::CHANGE_RETURN_STATUS);
                $result['notificationEmployeeByEmail'] = true;

            }
        }

        // Шлём клиентское уведомление, только если произошла смена статуса
        if ($notificationType === self::TYPE_CHANGE && !empty($data['differences']['to'])) {
            if (!empty($emailFrom) && !empty($client->email)) {
                MessagesClient::sendMessage([
                    0 => [ // MessageTypes::EMAIL
                           'emailFrom' => $emailFrom,
                           'emailTo'   => $client->email,
                           'subject'   => __('complaintClientEmailSubject', $templateData, $resellerId),
                           'message'   => __('complaintClientEmailBody', $templateData, $resellerId),
                    ],
                ], $resellerId, $client->id, NotificationEvents::CHANGE_RETURN_STATUS, (int)$data['differences']['to']);
                $result['notificationClientByEmail'] = true;
            }

            if (!empty($client->mobile)) {
                //если $error передаётся по ссылке
                $error = '';
                $res = NotificationManager::send($resellerId, $client->id, NotificationEvents::CHANGE_RETURN_STATUS, (int)$data['differences']['to'], $templateData, $error);
                if ($res) {
                    $result['notificationClientBySms']['isSent'] = true;
                }
                if (!empty($error)) {
                    $result['notificationClientBySms']['message'] = $error;
                }
            }
        }

        return $result;
    }
}
