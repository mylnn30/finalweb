<?php

namespace App\Entity;

use App\Repository\SystemSettingRepository;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: SystemSettingRepository::class)]
#[ORM\Table(name: 'system_settings', options: ['charset' => 'utf8mb4', 'collation' => 'utf8mb4_general_ci', 'engine' => 'InnoDB'])]
class SystemSetting
{
    #[ORM\Id]
    #[ORM\Column(name: 'setting_key', type: 'string', length: 50)]
    private string $settingKey;

    #[ORM\Column(name: 'setting_value', type: 'string', length: 255)]
    private string $settingValue;

    public function getSettingKey(): string
    {
        return $this->settingKey;
    }

    public function setSettingKey(string $settingKey): self
    {
        $this->settingKey = $settingKey;

        return $this;
    }

    public function getSettingValue(): string
    {
        return $this->settingValue;
    }

    public function setSettingValue(string $settingValue): self
    {
        $this->settingValue = $settingValue;

        return $this;
    }
}