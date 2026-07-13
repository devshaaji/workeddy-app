<?php

declare(strict_types=1);

namespace WorkEddy\Migrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260713090000_DisableMandatoryOtp extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Disables mandatory IAM login OTP by default and for existing stored settings.';
    }

    public function up(Schema $schema): void
    {
        if (!$schema->hasTable('system_settings')) {
            return;
        }

        $this->addSql(
            "UPDATE system_settings
             SET setting_value = '0'
             WHERE module = 'iam'
               AND setting_key = 'auth_otp_enabled'
               AND setting_value NOT IN ('0', 'false')"
        );
    }

    public function down(Schema $schema): void
    {
        if (!$schema->hasTable('system_settings')) {
            return;
        }

        $this->addSql(
            "UPDATE system_settings
             SET setting_value = '1'
             WHERE module = 'iam'
               AND setting_key = 'auth_otp_enabled'
               AND setting_value NOT IN ('1', 'true')"
        );
    }
}
