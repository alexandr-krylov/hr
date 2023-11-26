<?php

namespace NW\WebService\References\Operations\Notification;

/**
 * @property Seller $Seller
 */
class Contractor
{
    const TYPE_CUSTOMER = 0;
    public $id;
    public $type;
    public $name;
    public $mobile; //не было этого свойства
    public $email;  //не было этого свойства
    public ?Seller $Seller = null;

    /**
     * Если уж объект создаётся new self($resellerId) то в конструктор должен передаваться параметр
     */
    public function __construct(int $id, ?Seller $seller = null)
    {
        $this->id = $id;
        $this->Seller = $seller;
    }
    public static function getById(int $resellerId): self
    {
        return new self($resellerId); // fakes the getById method
    }

    public function getFullName(): string
    {
        return trim($this->name . ' ' . $this->id);     // trim чтобы если нет name или id возвращалась пустая строка - не пробел
    }
}
