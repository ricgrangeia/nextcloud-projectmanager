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
 * @method float getHoursPerWorkingDay()
 * @method void setHoursPerWorkingDay(float $hoursPerWorkingDay)
 * @method ?\DateTimeImmutable getCreatedAt()
 * @method void setCreatedAt(?\DateTimeImmutable $createdAt)
 * @method ?float getHourlyRate()
 * @method void setHourlyRate(?float $hourlyRate)
 * @method string getCurrencySymbol()
 * @method void setCurrencySymbol(string $currencySymbol)
 * @method bool getShowCostInSummary()
 * @method void setShowCostInSummary(bool $showCostInSummary)
 * @method bool getArchived()
 * @method void setArchived(bool $archived)
 * @method ?int getClientId()
 * @method void setClientId(?int $clientId)
 * @method string getPhase()
 * @method void setPhase(string $phase)
 * @method ?string getBrief()
 * @method void setBrief(?string $brief)
 * @method ?string getWaitingOnClient()
 * @method void setWaitingOnClient(?string $waitingOnClient)
 * @method int getUpdateEveryDays()
 * @method void setUpdateEveryDays(int $updateEveryDays)
 */
class Project extends Entity implements \JsonSerializable {
	public const PHASE_BRIEFING = 'briefing';
	public const PHASE_DEVELOPMENT = 'development';
	public const PHASE_INTERNAL_QA = 'internal_qa';
	public const PHASE_CLIENT_REVIEW = 'client_review';
	public const PHASE_DELIVERED = 'delivered';
	public const PHASE_CLOSED = 'closed';

	protected string $userId = '';
	protected string $name = '';
	protected float $hoursPerWorkingDay = 7.0;
	protected ?\DateTimeImmutable $createdAt = null;
	protected ?float $hourlyRate = null;
	protected string $currencySymbol = '€';
	protected bool $showCostInSummary = false;
	protected bool $archived = false;
	protected ?int $clientId = null;
	protected string $phase = self::PHASE_DEVELOPMENT;
	protected ?string $brief = '';
	protected ?string $waitingOnClient = '';
	protected int $updateEveryDays = 7;

	public function __construct() {
		$this->addType('userId', Types::STRING);
		$this->addType('name', Types::STRING);
		$this->addType('hoursPerWorkingDay', Types::FLOAT);
		$this->addType('createdAt', Types::DATETIME_IMMUTABLE);
		$this->addType('hourlyRate', Types::FLOAT);
		$this->addType('currencySymbol', Types::STRING);
		$this->addType('showCostInSummary', Types::BOOLEAN);
		$this->addType('archived', Types::BOOLEAN);
		$this->addType('clientId', Types::BIGINT);
		$this->addType('phase', Types::STRING);
		$this->addType('brief', Types::TEXT);
		$this->addType('waitingOnClient', Types::TEXT);
		$this->addType('updateEveryDays', Types::INTEGER);
	}

	public function jsonSerialize(): array {
		return [
			'id' => $this->id,
			'userId' => $this->userId,
			'name' => $this->name,
			'hoursPerWorkingDay' => $this->hoursPerWorkingDay,
			'createdAt' => $this->createdAt?->format(\DateTimeInterface::ATOM),
			'hourlyRate' => $this->hourlyRate,
			'currencySymbol' => $this->currencySymbol,
			'showCostInSummary' => $this->showCostInSummary,
			'archived' => $this->archived,
			'clientId' => $this->clientId,
			'phase' => $this->phase,
			'brief' => $this->brief ?? '',
			'waitingOnClient' => $this->waitingOnClient ?? '',
			'updateEveryDays' => $this->updateEveryDays,
		];
	}
}
