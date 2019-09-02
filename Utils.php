<?php

namespace LiamW\AccountDelete;

class Utils
{
	/**
	 * @return \LiamW\AccountDelete\XF\Entity\User|\XF\Entity\User
	 */
	public static function visitor()
	{
		return \XF::visitor();
	}
}