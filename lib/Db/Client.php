<?php

declare(strict_types=1);

namespace OCA\ProjectManager\Db;

use OCP\AppFramework\Db\Entity;
use OCP\DB\Types;

/**
 * @method string getUserId()
 * @method void setUserId(string $userId)
 * @method string getName()
 * @method void setName(string $name)
 * @method ?\DateTimeImmutable getCreatedAt()
 * @method void setCreatedAt(?\DateTimeImmutable $createdAt)
 * @method ?float getHourlyRate()
 * @method void setHourlyRate(?float $hourlyRate)
 * @method string getCurrencySymbol()
 * @method void setCurrencySymbol(string $currencySymbol)
 */
class Client extends Entity implements \JsonSerializable {
	protected string $userId = '';
	protected string $name = '';
	protected ?\DateTimeImmutable $createdAt = null;
	protected ?float $hourlyRate = null;
	protected string $currencySymbol = '€';

	public function __construct() {
		$this->addType('userId', Types::STRING);
		$this->addType('name', Types::STRING);
		$this->addType('createdAt', Types::DATETIME_IMMUTABLE);
		$this->addType('hourlyRate', Types::FLOAT);
		$this->addType('currencySymbol', Types::STRING);
	}

	public function jsonSerialize(): array {
		return [
			'id' => $this->id,
			'userId' => $this->userId,
			'name' => $this->name,
			'createdAt' => $this->createdAt?->format(\DateTimeInterface::ATOM),
			'hourlyRate' => $this->hourlyRate,
			'currencySymbol' => $this->currencySymbol,
		];
	}
}
