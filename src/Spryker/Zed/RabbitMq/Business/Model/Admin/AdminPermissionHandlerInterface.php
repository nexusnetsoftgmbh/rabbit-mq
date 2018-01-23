<?php

/**
 * Copyright © 2016-present Spryker Systems GmbH. All rights reserved.
 * Use of this software requires acceptance of the Evaluation License Agreement. See LICENSE file.
 */

namespace Spryker\Zed\RabbitMq\Business\Model\Admin;

use Psr\Log\LoggerInterface;

interface AdminPermissionHandlerInterface
{
    /**
     * @return bool
     */
    public function setPermissions(LoggerInterface $logger);
}
