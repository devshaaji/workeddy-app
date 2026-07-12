<?php

declare(strict_types=1);

namespace WorkEddy\Modules\Notification\Application;

use WorkEddy\Modules\Customer\Domain\Contracts\ICustomerRepository;
use WorkEddy\Modules\Notification\Domain\NotificationRecipient;

final class CustomerNotificationRecipientFactory
{
    public function __construct(
        private readonly ICustomerRepository $customers,
    ) {}

    public function fromCustomerId(int|string $customerId): ?NotificationRecipient
    {
        $reference = trim((string) $customerId);
        if ($reference === '') {
            return null;
        }

        $customer = ctype_digit($reference)
            ? $this->customers->findById((int) $reference)
            : $this->customers->findByUuid($reference);
        if ($customer === null) {
            return null;
        }

        $email = null;
        $phone = null;
        foreach ($this->customers->getContacts($customer->id) as $contact) {
            if ($contact->contactType === 'email' && $email === null) {
                $email = $contact->value;
            }
            if ($contact->contactType === 'phone' && $phone === null) {
                $phone = $contact->value;
            }
        }

        $name = trim(((string) ($customer->firstName ?? '')) . ' ' . ((string) ($customer->lastName ?? '')));
        if ($name === '') {
            $name = (string) ($customer->companyName ?? 'Customer');
        }

        return new NotificationRecipient(
            recipientId: (string) $customer->id,
            recipientType: 'customer',
            name: $name,
            email: $email,
            phone: $phone,
        );
    }
}
