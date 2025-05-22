<?php declare(strict_types = 1);

namespace Shredio\RequestMapper\Enum;

enum RequestLocation
{

	/** $_GET */
	case Query;
	/** $_POST */
	case Body;
	/** $_SERVER */
	case Header;
	case Attribute;
	/** $_SERVER */
	case Server;

}
