<?php declare(strict_types = 1);

namespace Shredio\RequestMapper\Request;

enum RequestLocation
{

	case Route;
	case Query;
	case Body;
	case Header;
	case Attribute;
	case Server;

}
