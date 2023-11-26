<?php

namespace NW\WebService\References\Operations\Notification;

function getResellerEmailFrom()
{
    return 'contractor@example.com';
}

function getEmailsByPermit($resellerId, $event)
{
    // fakes the method
    return ['someemeil@example.com', 'someemeil2@example.com'];
}

/**
 * Функция должна как-то формировать текстовое сообщение
 */
function __($message, $params, $id): string
{
    return '';
}