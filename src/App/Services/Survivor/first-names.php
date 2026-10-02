<?php

/**
 * Common U.S. first names and nicknames, for NameScanService. Accounts often
 * name someone by first name alone ("Tyler said..."), which NameDetector's
 * two-capitalised-words rule misses.
 *
 * 'ambiguous' names are also ordinary words (Will, Mark, Grace, May). They are
 * flagged only mid-sentence, where the capital letter means a name; at the
 * start of a sentence "Will you..." is left alone.
 *
 * Lower case. Over-reporting is fine: they confirm or remove each one.
 */
return [
    'names' => [
        // men's names and nicknames
        'aaron', 'abdul', 'adam', 'adrian', 'ahmed', 'aidan', 'aiden', 'alan', 'albert', 'alberto', 'alec', 'alejandro',
        'alex', 'alexander', 'alfred', 'ali', 'allen', 'alonzo', 'alvin', 'andre', 'andres', 'andrew', 'andy', 'angel',
        'angelo', 'anthony', 'antonio', 'armando', 'arthur', 'austin', 'barry', 'ben', 'benjamin', 'bernard', 'blake',
        'bobby', 'brad', 'bradley', 'brady', 'brandon', 'braxton', 'brayden', 'brendan', 'brent', 'brett', 'brian',
        'brody', 'bruce', 'bryan', 'bryce', 'caleb', 'calvin', 'cameron', 'carl', 'carlos', 'casey', 'cesar', 'chad',
        'charles', 'charlie', 'chris', 'christian', 'christopher', 'clarence', 'clayton', 'cody', 'colby', 'colin',
        'collin', 'connor', 'conor', 'corey', 'cory', 'craig', 'curtis', 'dakota', 'dallas', 'dalton', 'damian', 'damon',
        'dan', 'daniel', 'danny', 'dante', 'darius', 'darnell', 'darren', 'darryl', 'dave', 'david', 'deandre', 'derek',
        'derrick', 'deshawn', 'devin', 'devon', 'diego', 'dominic', 'don', 'donald', 'donovan', 'douglas', 'dustin',
        'dwayne', 'dylan', 'eddie', 'edgar', 'eduardo', 'edward', 'edwin', 'eli', 'elias', 'elijah', 'elliot', 'emmanuel',
        'enrique', 'eric', 'erik', 'ernest', 'ethan', 'eugene', 'evan', 'ezra', 'felix', 'fernando', 'francis',
        'francisco', 'gabriel', 'garrett', 'gary', 'gavin', 'george', 'gerald', 'gilbert', 'gordon', 'grayson', 'greg',
        'gregory', 'harold', 'harrison', 'harry', 'hector', 'henry', 'howard', 'hudson', 'hugo', 'ian', 'isaac', 'isaiah',
        'ivan', 'jace', 'jack', 'jackson', 'jacob', 'jaden', 'jaime', 'jake', 'jamal', 'james', 'jamie', 'jared',
        'jason', 'javier', 'jay', 'jayden', 'jeff', 'jeffrey', 'jeremiah', 'jeremy', 'jerome', 'jerry', 'jesse', 'jesus',
        'jim', 'jimmy', 'joe', 'joel', 'joey', 'john', 'johnny', 'jon', 'jonah', 'jonathan', 'jordan', 'jorge', 'jose',
        'joseph', 'josh', 'joshua', 'josiah', 'juan', 'julian', 'julio', 'justin', 'kai', 'kaleb', 'keith', 'kelvin',
        'ken', 'kenneth', 'kenny', 'kevin', 'kyle', 'landon', 'larry', 'lawrence', 'leo', 'leon', 'leonard', 'levi',
        'liam', 'logan', 'lorenzo', 'louis', 'lucas', 'luis', 'luke', 'malcolm', 'malik', 'manuel', 'marcus', 'mario',
        'martin', 'marvin', 'mateo', 'matt', 'matthew', 'maurice', 'micah', 'michael', 'miguel', 'mike', 'mitchell',
        'mohamed', 'mohammed', 'muhammad', 'nate', 'nathan', 'nathaniel', 'neil', 'nicholas', 'nick', 'nicolas', 'noah',
        'nolan', 'omar', 'oscar', 'owen', 'pablo', 'patrick', 'paul', 'pedro', 'peter', 'philip', 'phillip', 'preston',
        'quentin', 'rafael', 'ralph', 'ramon', 'randall', 'randy', 'raul', 'raymond', 'reggie', 'richard', 'ricardo',
        'ricky', 'riley', 'robert', 'roberto', 'rodney', 'roger', 'roman', 'ronald', 'ronnie', 'ross', 'roy', 'ruben',
        'russell', 'ryan', 'sam', 'samuel', 'santiago', 'scott', 'sean', 'sebastian', 'sergio', 'seth', 'shane',
        'shaun', 'shawn', 'simon', 'spencer', 'stanley', 'stephen', 'steve', 'steven', 'stuart', 'tanner', 'terrell',
        'terrence', 'terry', 'theo', 'theodore', 'thomas', 'tim', 'timothy', 'todd', 'tom', 'tommy', 'tony', 'travis',
        'trent', 'trevor', 'trey', 'tristan', 'troy', 'tyler', 'tyrone', 'tyson', 'victor', 'vincent', 'walter',
        'warren', 'wayne', 'wesley', 'william', 'wyatt', 'xavier', 'zachary', 'zach', 'zack', 'zane',
        // women's names and nicknames
        'aaliyah', 'abby', 'abigail', 'addison', 'adriana', 'alana', 'alexa', 'alexandra', 'alexis', 'alicia', 'alina',
        'alison', 'allison', 'ally', 'alyssa', 'amanda', 'amelia', 'amy', 'ana', 'andrea', 'angela', 'angelica',
        'angelina', 'anna', 'annie', 'ariana', 'arianna', 'ashley', 'ashlyn', 'aubrey', 'audrey', 'ava', 'avery',
        'bailey', 'barbara', 'becky', 'bella', 'beth', 'bethany', 'betty', 'bianca', 'brenda', 'brianna', 'bridget',
        'brittany', 'brooklyn', 'caitlin', 'camila', 'carla', 'carmen', 'caroline', 'carolyn', 'cassandra', 'cassidy',
        'catherine', 'chelsea', 'cheryl', 'chloe', 'christina', 'christine', 'cindy', 'claire', 'clara', 'claudia',
        'courtney', 'cynthia', 'dana', 'daniela', 'danielle', 'deborah', 'debra', 'denise', 'diana', 'diane', 'donna',
        'dorothy', 'elena', 'eleanor', 'elise', 'elizabeth', 'ella', 'ellie', 'emily', 'emma', 'erica', 'erika', 'erin',
        'esther', 'eva', 'evelyn', 'fatima', 'fiona', 'gabriela', 'gabriella', 'gabrielle', 'gianna', 'gina', 'gloria',
        'hailey', 'haley', 'hannah', 'harper', 'heather', 'heidi', 'helen', 'imani', 'isabel', 'isabella', 'isabelle',
        'jacqueline', 'jada', 'jamie', 'jane', 'janet', 'janice', 'jasmine', 'jean', 'jenna', 'jennifer', 'jenny',
        'jessica', 'jill', 'joan', 'jocelyn', 'josephine', 'joyce', 'judith', 'judy', 'julia', 'juliana', 'julie',
        'kaitlyn', 'kara', 'karen', 'karina', 'kate', 'katelyn', 'katherine', 'kathleen', 'kathryn', 'katie', 'kayla',
        'kaylee', 'keisha', 'kelly', 'kelsey', 'kendall', 'kendra', 'kennedy', 'kiara', 'kim', 'kimberly', 'kristen',
        'kylie', 'laura', 'lauren', 'layla', 'leah', 'lindsay', 'lindsey', 'linda', 'lisa', 'liz', 'lori', 'lucia', 'lucy',
        'lydia', 'mackenzie', 'madeline', 'madelyn', 'madison', 'maddie', 'makayla', 'mallory', 'margaret', 'maria',
        'mariah', 'marie', 'marissa', 'martha', 'mary', 'maya', 'megan', 'meghan', 'melanie', 'melissa', 'mia', 'michelle',
        'mikayla', 'miranda', 'molly', 'monica', 'morgan', 'nadia', 'nancy', 'naomi', 'natalia', 'natalie', 'nevaeh',
        'nia', 'nicole', 'nina', 'nora', 'olivia', 'paige', 'pamela', 'patricia', 'paula', 'peyton', 'priya', 'rachel',
        'rebecca', 'regina', 'riley', 'rita', 'samantha', 'sara', 'sarah', 'savannah', 'scarlett', 'selena', 'serena',
        'shannon', 'sharon', 'shelby', 'sierra', 'sofia', 'sophia', 'sophie', 'stella', 'stephanie', 'susan', 'sydney',
        'tamara', 'tanya', 'tara', 'taylor', 'teresa', 'tiffany', 'tina', 'valeria', 'valerie', 'vanessa', 'veronica',
        'victoria', 'vivian', 'wendy', 'whitney', 'yasmin', 'yolanda', 'zoe', 'zoey',
    ],

    'ambiguous' => [
        'amber', 'april', 'art', 'august', 'autumn', 'bill', 'bob', 'brooke', 'carol', 'chase', 'cliff', 'cole',
        'crystal', 'daisy', 'dawn', 'dean', 'destiny', 'drew', 'earl', 'faith', 'frank', 'gene', 'glen', 'grace', 'grant',
        'guy', 'harmony', 'hazel', 'holly', 'hope', 'hunter', 'iris', 'ivy', 'jade', 'jasper', 'joy', 'june', 'lane',
        'lily', 'mark', 'mason', 'max', 'may', 'melody', 'miles', 'parker', 'pat', 'pearl', 'penny', 'porter', 'ray',
        'rich', 'river', 'rob', 'rose', 'ruby', 'rusty', 'sandy', 'sky', 'sterling', 'summer', 'violet', 'wade', 'will',
    ],
];
