<?php

require_once dirname(__FILE__) . '/../../include/config.php';
require_once(IZNIK_BASE . '/include/db.php');
require_once(IZNIK_BASE . '/include/utils.php');
require_once(IZNIK_BASE . '/include/group/Group.php');
require_once(IZNIK_BASE . '/lib/geoPHP/geoPHP.inc');

$namemap = [
    [ 21223,'Aberdeen-Freegle','Scotland: Aberdeen [City Council]' ],
    [ 26792,'AberdeenshireWestFREEGLE','Scotland: Aberdeenshire West' ],
    [ 21227,'ambervalleye-recycle','E Mids: Amber Valley' ],
    [ 253469,'Anglesey_Freegle','Wales: Anglesey Freegle' ],
    [ 126632,'ArbroathFreegle','Scotland: ArbroathFreegle' ],
    [ 21231,'Armagh-Freegle','Northern Ireland: Armagh' ],
    [ 21232,'ascot-freegle','S. East: Ascot' ],
    [ 126527,'Ashfield-freegle','E. Mids: Ashfield [District Council]' ],
    [ 70146,'Ashford-Freegle','S. East: Ashford [Borough Council]' ],
    [ 253535,'Axe-Seat-n-Lyme-Freegle','"S. West: Ax, Seat n Lyme"' ],
    [ 21233,'Aylesbury_Recycle','S. East: Aylesbury' ],
    [ 43534,'BallymenaFreegle','Northern Ireland: Ballymena' ],
    [ 21235,'banbury_Freegle','S. East: Banbury Freegle' ],
    [ 21236,'BangorFreegle','Wales: Bangor Freegle' ],
    [ 21238,'BarkingandDagenham_Freegle','London: Barking and Dagenham' ],
    [ 21240,'barnsley_freegle','Yorkshire: Barnsley' ],
    [ 126728,'Barra-Freegle','Scotland: Barraigh' ],
    [ 21241,'Barrow-Freegle','N. West: Barrow in Furness' ],
    [ 126716,'Barton-recycling','Yorkshire: Barton Recycling' ],
    [ 21243,'BasingstokeFreegle','S. East: Basingstoke' ],
    [ 21244,'BathFreegle','S. West: Bath' ],
    [ 21245,'Battle-Freegle','S. East: Battle' ],
    [ 21247,'BCandSFreegle','W. Mids: Breewood Codsall & Stretton' ],
    [ 21250,'BedfordFreegle','East: BedfordFreegle' ],
    [ 21251,'BelfastUKFreegle','Northern Ireland: Belfast' ],
    [ 126557,'BerkoTringReuse','East: BerkoTringReuse' ],
    [ 144767,'Berwick-Upon-Tweed-Freegle','N. East: Berwick upon Tweed' ],
    [ 21254,'Bexhillfreegle','S. East: Bexhill' ],
    [ 21255,'Bexley-Freegle','London: Bexley' ],
    [ 21256,'Bicesterfreegle','S. East: Bicester Freegle' ],
    [ 126722,'Billingshurst-Freegle','S. East: Billingshurst' ],
    [ 21257,'BirminghamFreegle','W. Mids: Birmingham [City Council]' ],
    [ 21258,'Bishop-Auckland-Freegle','N. East: Bishop Auckland' ],
    [ 126683,'Bishops-Castle-Freegle','W. Mids: Bishops-Castle-Freegle' ],
    [ 21259,'Blaby-Freegle','E. Mids: Blaby' ],
    [ 21260,'Blackburn-with-Darwen-Freegle','N. West: Blackburn with Darwen' ],
    [ 21261,'blackcountryfreeworld_recycling','W. Mids: Dudley FreeWorld Recycling' ],
    [ 21262,'BlackpoolUKFreegle','N. West: Blackpool' ],
    [ 21265,'Blackwater-and-Yateley-Freegle','S. East: Blackwater & Yateley' ],
    [ 217449,'blaenaugwentfreegle','Wales: Blaenau Gwent' ],
    [ 21268,'BognorFreegle','S. East: Bognor' ],
    [ 21269,'BoltonFreegle','N. West: Bolton Freegle' ],
    [ 126638,'Borders-Freegle','Scotland: Scottish Borders' ],
    [ 126767,'Bordon-Alton-Petersfield-Freegle','S. East: Bordon-Alton-Petersfield-Freegle' ],
    [ 126548,'BournemouthFreegle','S. West: Bournemouth' ],
    [ 21273,'Bracknell-Freegle','S. East: Bracknell' ],
    [ 21274,'Bradford-on-Avon-Freegle','S. West: Bradford-on-Avon' ],
    [ 21275,'BradfordFreegle','N. West: Bradford' ],
    [ 359155,'BrecklandFreegle','East: Breckland' ],
    [ 21276,'breconhayrecycle','Wales: Brecon & Hay' ],
    [ 21277,'Brent-Freegle','London: Brent' ],
    [ 21278,'Brentwood-Freegle','East: Brentwood-Freegle' ],
    [ 21280,'Bridgend-Freegle','Wales: Bridgend [Council]' ],
    [ 21282,'bridlingtonfreegle','Yorkshire: Bridlington & Driffield' ],
    [ 359158,'BroadlandFreegle','East: Broadlands' ],
    [ 21283,'Bromley-Freegle','London: Bromley' ],
    [ 126692,'Burnham-on-Sea-Freegle','S. West: Burnham on Sea' ],
    [ 126614,'burnleyandpendlerealcycle','N. West: Burnley and Pendle Realcycle' ],
    [ 126584,'burwash-freegle','S. East: Burwash' ],
    [ 21287,'Bury-Freegle','N. West: Bury' ],
    [ 21291,'buxton-freegle','E Mids: Buxton' ],
    [ 21294,'Caerphilly_Freegle','Wales: Caerphilly [Council]' ],
    [ 21296,'CalderdaleRecycle','Yorkshire: Calderdale' ],
    [ 21300,'Camberley-Surrey-Heath-Freegle','S. East: Surrey Heath (Camberley)' ],
    [ 253559,'camborne_redruth_freegle','S. West: Camborne & Redruth' ],
    [ 21301,'Cambridge-Freegle','East: Cambridge-Freegle' ],
    [ 45523,'CAMDENSOUTH_FREEGLE','London: Camden South' ],
    [ 21305,'cannockfreegleuk','W. Mids: Cannock [Chase District Council]' ],
    [ 21306,'canterburyfreegle','S. East: Canterbury' ],
    [ 21308,'Cardiff-Freegle','Wales: Cardiff' ],
    [ 126755,'Cardigan-Freegle','Wales: Cardigan Freegle' ],
    [ 253520,'Carlisle-Freegle','N. West: Carlisle' ],
    [ 21310,'CarnforthFreegle','N. West: Carnforth' ],
    [ 253472,'Causeway_Freegle','Northern Ireland: Causeway Freegle - Coleraine Triangle' ],
    [ 21313,'CentralFifeFreegle','Scotland: Central Fife Freegle' ],
    [ 253544,'Chard-Beaminster-Bridport-Freegle','S. West: Chard Beaminster Bridport' ],
    [ 21316,'Chelmsford-Freegle','East: Chelmsford-Freegle' ],
    [ 144861,'Chelmsley-Wood-Freegle','W. Mids: Chelmsley-Wood-Freegle' ],
    [ 126764,'Cheltenham-Freegle','S. West: Cheltenham' ],
    [ 21317,'Cherwell_Valley_Freegle','S. East: Cherwell Valley Freegle' ],
    [ 45856,'CheshuntandWalthamCross-Freegle','S. East: Cheshunt & Waltham Cross' ],
    [ 21319,'Cheslyn_Hay_Freeworld-Recycling','W. Mids: Cheslyn Hay' ],
    [ 21320,'Chesterfield-Freegle','E Mids: Chesterfield' ],
    [ 126659,'ChesterFreegle','N. West: Chester' ],
    [ 21323,'ChilternFreegle','S. East: Chiltern' ],
    [ 21324,'chippyfreegle','S. East: chippyfreegle' ],
    [ 253556,'Colchester-Freegle','East: Colchester-Freegle' ],
    [ 21328,'Congleton-Freegle','N. West Congleton' ],
    [ 21329,'conwyfreegle','Wales: Conwy [Council]' ],
    [ 126611,'coventryfreegle','W. Mids: Coventry' ],
    [ 253541,'Cowal-Peninsula-Freegle','Scotland: Cowal Peninsula' ],
    [ 21330,'Cramlington','N. East: Cramlington' ],
    [ 21331,'Crawley-Freegle','S. East: Crawley' ],
    [ 126605,'Crewe-Nantwich-Freegle','N. West: Crewe & Nantwich' ],
    [ 21332,'crowboroughfreegle','S. East: Crowborough' ],
    [ 21333,'Croydon-Freegle','London: Croydon' ],
    [ 126701,'Darlington-Freegle','N. East: Darlington' ],
    [ 21335,'dartfordfreegle','S. East: Dartford' ],
    [ 253514,'Dartmouth-and-Kingsbridge-Freegle','S. West: Dartmouth and Kingsbridge' ],
    [ 21336,'DaventryFreegle','E Mids: Daventry' ],
    [ 21339,'derbyfreegle','E Mids: Derby' ],
    [ 21340,'Derwentside-Freegle','N. East: Derwentside' ],
    [ 21341,'Devizes-Freegle','S. West: Devizes' ],
    [ 21342,'DewsburyFreegle','Yorkshire: Dewsbury Freegle' ],
    [ 21345,'Doncaster-Freegle','Yorkshire: Doncaster' ],
    [ 126749,'Dumfries-Galloway_Freegle','Scotland: Dumfries and Galloway [Council]' ],
    [ 21347,'Dunstable-Freegle','East: Dunstable' ],
    [ 21348,'durham_freegle','N. East: Durham' ],
    [ 21349,'DursleyFreegle','S. West: Dursley' ],
    [ 21350,'Ealing-Freegle','London: Ealing' ],
    [ 253421,'East-Dunbarton-Freegle','Scotland: East Dunbartonshire' ],
    [ 126554,'East-Staffordshire-Freegle','W. Mids: East-Staffordshire-Freegle' ],
    [ 21352,'EastbourneFreegle','S. East: Eastbourne' ],
    [ 126686,'EastGrinsteadFreegle','S. East: East Grinstead' ],
    [ 21353,'Eastleigh-Freegle','S. East: Eastleigh' ],
    [ 46795,'EastwoodFreegle','E. Mids: Eastwood Freegle' ],
    [ 21354,'EdinburghFreegle','Scotland: Edinburgh' ],
    [ 21358,'Elmbridge-Recycle','S. East: Elmbridge' ],
    [ 21359,'Ely-Freegle','East: Ely-Freegle' ],
    [ 21361,'enfieldfreegle','London: Enfield' ],
    [ 126641,'EppingForestFreegle','East: eppingforestfreegle' ],
    [ 126677,'Exeter_Freegle','S. West: Exeter' ],
    [ 253568,'Falkirk_Free2go','Scotland: Falkirk' ],
    [ 253571,'Falmouth-Freegle','S. West: Falmouth' ],
    [ 21364,'fareham_freegle','S. East: Fareham' ],
    [ 21365,'Farnborough-Aldershot-Freegle','S. East Farnborough and Aldershot' ],
    [ 21367,'Feltham-Bedfont-Hanworth-Freegle','"London: Feltham, Bedfont and Hanwoth"' ],
    [ 21368,'FenlandFreegle','East: fenlandfreegle' ],
    [ 126731,'FermanaghFreegle','Northern Ireland: Fermanagh' ],
    [ 21370,'Filton-Patchway-Stokes-Freegle','S. West: Filton-Patchway-Stokes-Freegle' ],
    [ 253424,'FlintshireFreegle','Wales: Flintshire' ],
    [ 126665,'FolkestoneFreegle','S. East: Folkestone' ],
    [ 253457,'foylefreegle','Northern Ireland: Foyle' ],
    [ 253427,'FreebleAyr','Scotland: Ayr' ],
    [ 192307,'freegle_hinckley_and_bosworth','E. Mids: Hinckley and Bosworth' ],
    [ 21427,'freegle_redbridge','London: Redbridge' ],
    [ 253451,'freegle_seleicestershire','E. Mids: South East Leicestershire' ],
    [ 253538,'Freegle-Bristol','S. West: Bristol' ],
    [ 253523,'freegle-bromsgrove','W. Mids: Bromsgrove [District Council]' ],
    [ 253430,'Freegle-Charnwood','E Mids: Charnwood' ],
    [ 21405,'freegle-chorley','N. West: Chorley' ],
    [ 21406,'freegle-kingston','London: Kingston' ],
    [ 260843,'freegle-leamington','W. Mids Leamington Spa [Warwick District Council]' ],
    [ 253454,'freegle-leicester','E. Mids: Leicester' ],
    [ 126752,'Freegle-Lincoln','E. Mids: Lincoln' ],
    [ 253526,'freegle-redditch','W. Mids: Redditch [Borough Council]' ],
    [ 21410,'freegle-southport','N. West: Southport' ],
    [ 21413,'FreegleClacksUK','Scotland: Clacks [Clackmannanshire Council]' ],
    [ 21414,'freegledundee','Scotland: Dundee' ],
    [ 21415,'FreegleFreshers','Training Group' ],
    [ 253496,'FreeglePerthSouthUK','Scotland: Perth South Freegle' ],
    [ 21421,'freeglesthelensborough','N. West: St Helens Borough' ],
    [ 21423,'FreegleThanet','S. East: Thanet' ],
    [ 21424,'FreegleTorfaenUK','Wales: Torfaen' ],
    [ 21429,'FreeShareWakefieldUK','Yorkshire: Wakefield' ],
    [ 353440,'fromefreegle','S. West: Frome' ],
    [ 21434,'Gateshead_Freegle','N. East: Gateshead' ],
    [ 21435,'glasgow-freeshare','Scotland: Glasgow' ],
    [ 126581,'Glossop-Freegle','E Mids: Glossop' ],
    [ 21436,'gloucesterfreegle','S. West: Gloucester City' ],
    [ 28904,'Gordano-Valley-Freegle','S. West: Gordano-Valley-Freegle' ],
    [ 21437,'Gosport-Freegle','S. East: Gosport' ],
    [ 126560,'grangemouthfreegle','Scotland: Grangemouth' ],
    [ 21439,'Gravesend_Freegle','S. East: Gravesend' ],
    [ 359161,'GreatYarmouthFreegle','East: Great Yarmouth' ],
    [ 126680,'GreenCycleAdur','S. East: Adur' ],
    [ 126695,'GreenCycleHove','S. East: Hove' ],
    [ 218359,'GreenCyclePortslade','S. East: Portslade' ],
    [ 21441,'greencyclesussex','S. East: Brighton' ],
    [ 253562,'Greenwich-Freegle','London: Greenwich Freegle' ],
    [ 21443,'Grimsbyfreegle','East: Grimsby' ],
    [ 21446,'guildfordrecycleforfree','S. East: Guildford' ],
    [ 21449,'Hackney-Freegle','London: Hackney' ],
    [ 21450,'hammersmithandfulhamfreegle','London: Hammersmith and Fulham' ],
    [ 217446,'haringey-freegle','London: Haringey Freegle' ],
    [ 21453,'Harrogate-Freegle','Yorkshire: Harrogate' ],
    [ 21454,'Harrow-Freegle','London: Harrow' ],
    [ 21455,'Hastings-Freegle','S. East: Hastings' ],
    [ 21456,'Hatfield-Freegle','East: Hatfield-Freegle' ],
    [ 21457,'HavantFreegle','S. East: Havant' ],
    [ 21458,'Havering-Freegle','London: Havering' ],
    [ 126536,'HayleFreegle','S. West: Hayle and St Ives' ],
    [ 21460,'haywardsheath-freegle','S. East: Haywards Heath' ],
    [ 126647,'helensburgh-freegle','Scotland: Helensburgh' ],
    [ 21461,'Helston-Freegle','S. West: Helston' ],
    [ 21462,'Hemel-Freegle','East: Hemel-Freegle' ],
    [ 21463,'Henfield-Freegle','S. East Henfield' ],
    [ 21464,'HenleyFreegle','S. East: Henley' ],
    [ 21465,'Hereford_Freegle','W. Mids: Hereford' ],
    [ 21467,'hertford_freegle','East: hertford_freegle' ],
    [ 21469,'high_wycombe_freegle','S. East: High Wycombe' ],
    [ 49861,'HillingdonFreegle','London: Hillingdon' ],
    [ 21470,'Hounslow-Recycle','London: Hounslow' ],
    [ 21471,'HuddersfieldRecycle','Yorkshire: Huddersfield' ],
    [ 21473,'HullFreegle','Yorkshire: Hull' ],
    [ 126698,'HungerfordFreegle','S. West: Hungerford' ],
    [ 21475,'huntsfreegle','East: huntsfreegle' ],
    [ 126668,'hyndburnrealcycle','N. West: Hyndburn' ],
    [ 21483,'IpswichRecycle','East: ipswichrecycle' ],
    [ 21485,'Isle-of-Man-Freegle','N West: Isle of Man' ],
    [ 126608,'Isle-of-Wight-Freegle','S. East: Isle of Wight' ],
    [ 21491,'KendalFreegle','N. West: Kendal' ],
    [ 126620,'Kenilworth-Freegle','W. Mids: Kenilworth' ],
    [ 21492,'Kensington-Chelsea-Freegle','London: Kensington and Chelsea' ],
    [ 253433,'kentishtown_freegle','London: Kentish Town' ],
    [ 21493,'Keswick-Cockermouth-Freegle','N. West: Keswick and Cockermouth' ],
    [ 126602,'KetteringUKFreegle','E. Mids: Kettering' ],
    [ 126707,'Kintyre-Recycling','Scotland: Kintyre' ],
    [ 126734,'Lambeth-Freegle','London: Lambeth' ],
    [ 126737,'Lanark-Freegle','Scotland: Lanark' ],
    [ 21496,'Lancaster-Morecambe-Freegle','N. West: Lancaster-Morecombe' ],
    [ 21500,'Launceston-Freegle','S. West: Launceston' ],
    [ 21501,'leedsfreegle','Yorkshire: Leeds' ],
    [ 126743,'LeighFreegle','N. West: Leigh' ],
    [ 126551,'LeightonBuzzard_Freegle','East: LeightonBuzzard_Freegle' ],
    [ 21504,'Letchworth_Freegle','East: Letchworth_Freegle' ],
    [ 21505,'LewesFreegle','S. East: Lewes' ],
    [ 21311,'Lewisham-Freegle','London: Lewisham' ],
    [ 21508,'Lichfield_Freeworld_Recycling','W. Mids: Lichfield' ],
    [ 21509,'LiverpoolRecycle','N. West: Liverpool' ],
    [ 21510,'Livingston-Freegle','Scotland: Livingston' ],
    [ 253436,'LlandrindodFreegle','Wales: LLandrindod' ],
    [ 21512,'Llanelli-Freegle','Wales: LLanelli' ],
    [ 21513,'Llyn-Peninsula-Freegle','Wales: Llyn Peninsula' ],
    [ 253460,'Louth-Freegle','E. Mids: Louth' ],
    [ 21515,'Ludlow-Leominster-Freegle','W. Mids: Ludlow and Leominster' ],
    [ 21516,'Luton-Freegle','East: Luton' ],
    [ 21519,'Macclesfield-Freegle','N. West: Maccclesfield' ],
    [ 126758,'magherafelt_district_freegle','Northern Ireland: Magherafelt' ],
    [ 126740,'Malvern-Hills-Freegle','W. Mids: Malvern Hills' ],
    [ 21521,'manchestergreencycleuk','N. West: Manchester' ],
    [ 21523,'matlock-freegle','E. Mids: Matlock' ],
    [ 126593,'MearnsFreegle','Scotland: Mearns' ],
    [ 21525,'Medway-Freegle','S. East: Medway' ],
    [ 21526,'mertonfreegle','London: Merton' ],
    [ 21527,'MidDevonFreegle','S. West: Mid Devon' ],
    [ 21528,'middlesbrough-freegle','N. East: Middlesbrough' ],
    [ 126662,'Mildenhall-and-Lakenheath-Freegle','East: Mildenhall-and-Lakenheath-Freegle' ],
    [ 21529,'Milton-Keynes-Freegle','S. East: Milton Keynes (Borough)' ],
    [ 21531,'Minehead-Exmoor-Freegle','S. West: Minehead & Exmoor' ],
    [ 378098,'Moray-Freegle','Scotland: Moray' ],
    [ 78435,'Morpeth','N. East: Morpeth' ],
    [ 126689,'NeathPortTalbotFreegle','Wales: Neath Port Talbot' ],
    [ 51553,'New-Forest-East-Freegle','S. East: New Forest East' ],
    [ 21535,'New-Forest-West-Freegle','S. East: New Forest West' ],
    [ 253439,'newburyfreegle','S. East: Newbury' ],
    [ 253517,'Newham-Reuse-Group','London: Newham' ],
    [ 171793,'newquay-freegle','S. West: Newquay' ],
    [ 21546,'North_Shropshire_Freegle','"N. West: N. Shropshire [Market Drayton, Whitchurch & Wem]"' ],
    [ 126545,'North_Tyneside','N. East: North Tyneside' ],
    [ 126617,'North-Warwickshire-Freegle','W. Mids: North Warwickshire' ],
    [ 21540,'Northampton_East_Freegle','E. Mids: Northampton East Freegle' ],
    [ 21537,'NORTHAMPTONSOUTHFREEGLE','E. Mids: Northampton South' ],
    [ 21538,'northamptonUKfreegle','E. Mids: Northampton North & Central Freegle' ],
    [ 21539,'NORTHAMPTONWESTFREEGLE','E. Mids: Northampton West Freegle' ],
    [ 21541,'northcotswoldfreegle_group','W. Mids: North Cotswold' ],
    [ 126596,'NorthDartmoorFreegle','S. West: North Dartmoor' ],
    [ 21542,'NorthDevonFreegle','S. West: North Devon' ],
    [ 176813,'northdown-ardspeninsula_greencycle','Northern Ireland: North Down & Ards Peninsula.' ],
    [ 359164,'NorthNorfolkFreegle','East: North Norfolk' ],
    [ 359170,'NorwichFreegle','East: Norwich' ],
    [ 21548,'nottinghamfreegle','E. Mids: Nottingham' ],
    [ 253442,'NWLeicestershirefreegle','E. Mids. North West Leicestershire' ],
    [ 21551,'OldhamFreegle','N. West: Oldham' ],
    [ 21553,'Oswestry_Freegle','W. Mids: Oswestry Freegle' ],
    [ 21554,'Otley-Freegle','Yorkshire: Otley' ],
    [ 21555,'Oxford-Freegle','S. East: Oxford Freegle' ],
    [ 126587,'Paisley-Freegle','Scotland: Paisley' ],
    [ 21559,'penkridge_freeworld-recycling','W. Mids: Penkridge' ],
    [ 21560,'PenrithEdenFreegle','N. West Penrith & Eden' ],
    [ 21561,'PenzanceFreegle','S. West: Penzance' ],
    [ 21562,'peterborough-freegle','East: peterborough-freegle' ],
    [ 21567,'Plymouth-Freegle','S. West: Plymouth' ],
    [ 21570,'pools-freegle','N. East: Hartlepool' ],
    [ 21571,'Porthmadogrecycle','Wales: Porthmadog Freegle' ],
    [ 21572,'Portsmouth_Freegle','S. East: Portsmouth' ],
    [ 253445,'potteries_freegle','W. Mids Potteries [Stoke-on-Trent City Council]' ],
    [ 126671,'Presteigne-Freegle','Wales: Presteigne' ],
    [ 21573,'prestonfreegle','N. West: Preston' ],
    [ 253511,'PurbeckFreegle','S. West: Purbeck' ],
    [ 61840,'Queensferry-Freegle','Scotland: Queensferry' ],
    [ 21579,'ReadingFreegleUK','S. East: Reading' ],
    [ 21581,'RealcycleRhonddaCynonTaf','Wales: Rhondda Cynon Taf [Council]' ],
    [ 21582,'RecycleGiftingMidSussex','S. East: Burgess Hill' ],
    [ 21585,'redcar-cleveland-freegle','N. East Redcar and Cleveland' ],
    [ 126713,'Redhill-Freegle','S.East: Redhill [Reigate & Merstham]' ],
    [ 21589,'ribblevalleyfreegle','N. West: Ribble Valley' ],
    [ 253475,'richmonduponthamesrecycle','London: Richmond upon Thames' ],
    [ 21590,'RickmansworthFreegle','East: RickmansworthFreegle' ],
    [ 21592,'River-Meadow-Freegleland','S. West: Chippenham/River Meadow' ],
    [ 21593,'Rochdalefreegle','N. West: Rochdale' ],
    [ 21594,'Rochford-and-Rayleigh-Freegle','East: Rochford-and-Rayleigh-Freegle' ],
    [ 126704,'Rossendale-Freegle','N. West: Rossendale' ],
    [ 21595,'Rotherham-Freegle','Yorkshire: Rotherham' ],
    [ 253448,'rothwell-desboroughfreegle','E. Mids: Rothwell-Desborough Freegle' ],
    [ 21596,'Royston-Freegle','East: Royston-Freegle' ],
    [ 21598,'RugbyFreegle','W. Mids: Rugby' ],
    [ 126623,'rugeleyfreegle','W. Mids: Rugeley Freegle' ],
    [ 253487,'runnymede_freegle','S. East: Runnymede' ],
    [ 21599,'RushdenHighamFreegle','E. Mids: Rushden and Higham Ferrers Freegle' ],
    [ 126770,'Rutland-Freegle','E. Mids: Rutland' ],
    [ 253565,'Rye-Freegle','S. East: Rye' ],
    [ 21600,'ryedale_freegle','Yorkshire: Ryedale [District Council]' ],
    [ 21603,'Salford_Freegle','N. West: Salford' ],
    [ 126635,'Sandy-and-Biggleswade-Freegle','East: Sandy-and-Biggleswade-Freegle' ],
    [ 21604,'ScarboroughFreegle','Yorkshire: Scarborough [Borough Council]' ],
    [ 62692,'scunthorpefreegle','Yorkshire: Scunthorpe' ],
    [ 21606,'SeahousesAndAlnwickFreegle','N. East: Seahouses and Alnwick' ],
    [ 386071,'SelbyFreegle','Yorkshire: Selby Freegle' ],
    [ 21607,'Sevenoaks-Freegle','S. East: Sevenoaks' ],
    [ 21609,'Sheffield-Freegle','Yorkshire: Sheffield' ],
    [ 126650,'Sheppey_and_Sittingbourne_FREEGLE','S. East: Sheppey and Sittingbourne' ],
    [ 21612,'Shrewsbury_Freegle','W. Mids: Shrewsbury Freegle' ],
    [ 21614,'Skegness-Freegle','E. Mids: Skegness' ],
    [ 126575,'skiptoncraven-freegle','Yorkshire: Skipton & Craven [District Council]' ],
    [ 21615,'SkyeLochalsh-Freegle','Scotland: SkyeLochalsh-Freegle' ],
    [ 21616,'SloughFreegle','S. East: Slough' ],
    [ 253478,'Solihullfreegle','W. Mids: Solihull' ],
    [ 253532,'South-Derbyshire-Freegle','E. Mids: South Derbyshire' ],
    [ 176810,'south-down-freegle','Northern Ireland: South Down Greencycle' ],
    [ 21618,'SouthLincsFreeRecyclers','E. Mids: South Lincs' ],
    [ 359173,'SouthNorfolkFreegle','East: South Norfolk' ],
    [ 214794,'southtyneside-freegle','N. East: South Tyneside' ],
    [ 253463,'southwark_freegle','London: Southwark' ],
    [ 21632,'st_albans_reuse','East: st_albans_reuse' ],
    [ 21619,'St-Austell-Freegle','S.West: St Austell' ],
    [ 126653,'St-Neots-Freegle','East: St-Neots-Freegle' ],
    [ 21621,'Stafford_Freegle','W. Mids: Stafford [Borough Council]' ],
    [ 21623,'StevenageFreegle','East: stevenagefreegle' ],
    [ 126656,'Steyning-Freegle','S. East: Steyning' ],
    [ 21625,'stirlingcityfreegle','Scotland: Stirling [Council]' ],
    [ 21628,'StockportFreegle','N. West: Stockport' ],
    [ 21629,'stockton-freegle','N. East: Stockton' ],
    [ 21630,'Stone-Freegle','W. Mids: Stone' ],
    [ 21631,'StroudFreegle','S. West: Stroud' ],
    [ 126599,'SudburyFreegle','East: SudburyFreegle' ],
    [ 253466,'sunderlandfreegle','N. East: Sunderland' ],
    [ 21635,'Swansea-Freegle','Wales: Swansea' ],
    [ 92103,'Swindon-Freegle','S. West: Swindon' ],
    [ 21644,'Tameside-Freegle','N. West: Tameside' ],
    [ 21645,'Tamworth_Freegle','W. Mids: Tamworth' ],
    [ 21646,'Taunton-Freegle','S. West: Taunton' ],
    [ 21650,'Teignbridge-Freegle','S. West: Teignbridge' ],
    [ 21651,'TelfordFreegle','W. Mids: Telford [and Wrekin Council]' ],
    [ 253574,'Tendring-Freegle','East: Tendring-Freegle' ],
    [ 126629,'Tenterden-Freegle','S. East: Tenterden' ],
    [ 21652,'Tewkesburyfreegle','S.West: Tewkesbury' ],
    [ 21655,'Thirsk-Northallerton-Reuse','Yorkshire: Northallerton & Thirsk' ],
    [ 190321,'thrapston-freegle','E. Mids: Thrapston Freegle' ],
    [ 21656,'ThurrockFreegle','East: ThurrockFreegle' ],
    [ 21658,'Tonbridge-Freegle','S. East: Tonbridge' ],
    [ 21659,'toon','N.East: Newcastle upon Tyne (Toon)' ],
    [ 126644,'Torbay-Freegle','S. West: Torbay' ],
    [ 21661,'Torridge-Freegle','S. West: Torridge' ],
    [ 253499,'towcester-freegle','E. Mids: Towcester' ],
    [ 21662,'TowerHamletsRecycle','London: Tower Hamlets' ],
    [ 21665,'trafford_freegle','N. West: Trafford' ],
    [ 21666,'Truro-Freegle','S. West: Truro' ],
    [ 21667,'TunbridgeWellsFreegle','S. East: Tunbridge Wells [Borough Council]' ],
    [ 21668,'UckfieldFreegle','S. East: Uckfield' ],
    [ 21670,'Vale-of-Glamorgan-Freegle','Wales: Vale of Glamorgan' ],
    [ 21671,'ValeWhiteHorse-Freegle','S. East: Vale of White Horse' ],
    [ 126725,'Wadebridge-Freegle','S. West: Wadebridge' ],
    [ 21675,'Walsall_Freeworld-Recycling','W. Mids: Walsall [Borough Council]' ],
    [ 21676,'WalthamForestFreegle','London: Waltham Forest' ],
    [ 126719,'Wandsworth-Freegle','London: Wandsworth Town' ],
    [ 21677,'WarminsterFreegle','S. West: Warminster' ],
    [ 21680,'WarringtonFreegle','N. West: Warrington' ],
    [ 21681,'washington_freegle','N. East: Washington' ],
    [ 126569,'waste-not-want-not-north-dorset','S. West: North Dorset' ],
    [ 21682,'Watford-Freegle','East: Watford-Freegle' ],
    [ 359176,'WaveneyFreegle','East: Waveney' ],
    [ 21684,'Wellingboroughfreegle','E. Mids: Wellingborough Freegle' ],
    [ 21685,'WelwynGardenCityFreegle','East: WelwynGardenCityFreegle' ],
    [ 126626,'WestKingsdownFreegle','S. East: West Kingsdown & Swanley' ],
    [ 64894,'WESTMINSTERUKFREEGLE','London: Westminster' ],
    [ 359179,'WestNorfolkFreegle','East: West Norfolk' ],
    [ 126539,'WhiteCliffsFreegle','S. East: WhiteCliffsFreegle' ],
    [ 21687,'Whitehaven-Freegle','N. West: Whitehaven' ],
    [ 126572,'WiganFreegleUK','N. West: Wigan' ],
    [ 21689,'Wilmslow_Freegle','N. West: Wilmslow' ],
    [ 21690,'Wimborne-Freegle','S.West: Wimborne' ],
    [ 21691,'windsor-maidenhead-freegle','S. East: Windsor and Maidenhead' ],
    [ 21693,'Wirral-Freegle','N. West: Wirral' ],
    [ 21694,'Witney-Freegle','S. East: Witney' ],
    [ 21695,'WNM_Freegle','"Wales: Welshpool, Newtown & Montgomery"' ],
    [ 21696,'woking-freegle','S. East: Woking' ],
    [ 21698,'Wokingham-Freegle','S. East: Wokingham [Borough Council]' ],
    [ 21699,'WolvesFreegle','W. Mids: Wolverhampton [City Council]' ],
    [ 21700,'WoodleyFreegle','S. East: Woodley' ],
    [ 21701,'Worcester-Freegle','W. Mids: Worcester City' ],
    [ 126710,'Worthing-Freegle','S. East: Worthing' ],
    [ 21703,'WoSFreegle','East: wosfreegle' ],
    [ 21704,'Wrexham-Freegle','Wales: Wrexham [Council]' ],
    [ 21705,'wyreforestfreegle','W. Mids: Wyre Forest [District Council]' ],
    [ 21706,'Yeovil-Freegle','S. West: Yeovil' ],
    [ 21707,'York-Freegle','Yorkshire: York' ]
];

$kml = simplexml_load_file(GATKML);
$g = Group::get($dbhr, $dbhm);

if ($kml) {
    $kgroups = $kml->Document->Folder->children();

    foreach ($kgroups as $kgroup) {
        $kname = trim($kgroup->name);
        $poly = $kgroup->Polygon;
        #error_log(var_export($kgroup, TRUE));
        if ($poly) {
            $geom = geoPHP::load($poly->asXML(), 'kml');
            $wkt = $geom->out('wkt');
            if (strlen(trim($wkt)) > 0) {
                #error_log("WKT from GAT $wkt");
                $found = FALSE;
                foreach ($namemap as $name) {
                    #error_log("Compare {$name[2]} vs $kname");
                    if (strpos($kname, $name[2]) === 0) {
                        error_log("Found $kname as {$name[0]} {$name[1]}");
                        $found = TRUE;
                        $dbhm->preExec("UPDATE groups SET polyofficial = ? WHERE id = ?;", [ $wkt, $name[0]] );
                    }
                }

                if (!$found) {
                    error_log("Failed to find $kname");
                }
            }
        } else {
            #error_log("No WKT from GAT for $kname");
        }
    }
} else {
    error_log("Failed to get KML");
}