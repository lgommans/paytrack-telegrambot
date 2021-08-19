<?php 
$welcomeMessages = ['Paytrack is simple: give me a name and number. I keep track who owes you and whom you owe.',
	"To track that Sally owes you 5 dollars, message me:\nSally 5\nTo track that you owe her 5 dollars, message me:\nSally -5\nTo see someone's balance, just message me the name, for example:\nSally",
	'For more info, message me: /help or /examples',
];

$groupWelcomeMessages = ["Hello! For info about using me, send me a PM with /help or /helpgroup."];

$helpMessages = ["Main features (see also /examples):\n\n"
	. "To track that Sally owes you 5 dollars, message me:\nSally 5\nTo track that you owe her 5 dollars, message me:\nSally -5\nTo see someone's balance, just message me the name, for example:\nSally\n\n"
	. "Optionally, you can specify a description after a colon. For example, messsage me:\nSally 5: pizza\nTo remember that it was for pizza.\n\n"
	. "You can message me /total to see who still owes you, whom you still owe and the total amount. People with 0 balance are not shown. To show those, use /totalall\n\n",

	"Connecting:\n"
	. "To notify someone whenever you add a debt, use `/connect [name]`, like `/connect Sally`. This will give you a link which the other person (Sally) has to open (a bot cannot contact someone, they have to"
	. "contact me first). If you both send connect links to each other (two way connect), your accounts will be synchronized. If you add a debt, not only will they see it, but their debt value for you is"
	. "also updated. Note that if you have unequal balances, they will be reset to zero to sync up.\n\n"
	. "To break the connection to someone, use `/disconnect [name]`, like `/disconnect Sally`.\n\n"
	. "To remind someone of their debt, use `/remind [name]`, like `/remind Sally`. You can also add a description:\n`/remind Sally: could you pay me back next Thursday?`\n\n",

	"Decimals are denoted by dots; do not use a thousand separator. For example 1.95 (not 1,95!) and 9000 (not 9,000!).\n\n"
	. "See /helpgroup for usage in a group chat.\n"
	. "See /about for privacy policy, the author, support, etc."
];

$aboutMessages = ["This bot is made and hosted by $botOwner.\n\nI wanted a simple way to keep track of who owes me, and I thought it might be useful to others as well. "
	. "If you need help or would like to request a feature, feel free to contact me.\n\n",

	"Data and privacy:\nThe bot stores your name and user id, plus (of course) any names you tell it (e.g. 'Sally 5' will store the name 'Sally'). Descriptions are not stored, they are solely for your "
	. "own reference (and anyone you connect to); history is not stored; and if you `/rename` someone, the old name is permanently forgotten.\n\n"
	. "This data is only stored on the server (in the Netherlands with strict privacy laws) and sent via Telegram. The only purpose of the data is to provide the features mentioned in /help.\n\n"
	. "Since providing the service costs me no money, I have no intention of selling your data or showing ads. Things might change with ten thousand users, but you will be warned long in advance and "
	. "will always have a way to opt out.\n\n",

	"Other info:\n"
	. "The bot will never message you unless: 1) you message the bot first; or 2) someone you connected to (with `/connect`) updates your balance.\n\n"
	. "It is currently not possible to delete your data from the server because I have not made that feature yet (only me and my friends currently use the bot). Ask me and I will "
	. "add that option.\n\n"
	. "The source code of the bot can be made public on request. Currently it's not open source just because the code not very pretty.\n\n"
	. "Translations can be added on request."
];

$exampleMessages = ["Sally owes you 5 bucks:\n"
	. "*you:* sally 5.33\n"
	. "*bot:* Ok, you owe 5.33 to sally.\n\n"

	. "Sally pays you 5 back:\n"
	. "*you:* sally -5\n"
	. "*bot:* Ok. Updated amount: sally owes you 0.33\n\n"

	. "You went for dinner with Sally and Joe (3 people total) for 60 bucks and you divide the cost. Sally paid, so you owe her:\n"
	. "*you:* sally -60/3\n"
	. "*bot:* Ok, you owe 20 to sally.\n\n"

	. "Don't forget to add `/$groupprefix` or `/$groupprefixshortcut` before each message if you use me in a group!"
];

$helpgroupMessages = ["In a group chat, I need to know when you are talking to me. The `/`-prefixed commands (like `/total`) work as usual if I was the last bot someone talked to or if I am made admin, "
	. "but for other commands you need to start with `/$groupprefix` or `/$groupprefixshortcut`.\n\n"

	. "Examples:\n"
	. "Add a debt of 5 for Sally: `/$groupprefixshortcut sally 5`\n"
	. "With description: `/$groupprefixshortcut sally 5: pizza`\n"
	. "Show Sally's debt: `/$groupprefixshortcut sally`\n\n"

	. "See /help for general usage information"
];


