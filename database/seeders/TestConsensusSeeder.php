<?php

namespace Database\Seeders;

use App\Models\DocumentSourceChunk;
use App\Models\UploadedDocumentSource;
use App\Models\ConversationGroup;
use App\Models\Conversation;
use Illuminate\Database\Seeder;

class TestConsensusSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $consensus_texts = [
            0 => 'As a group we have discerned that as an institutional church we are not providing enough pastoral accompaniment for families. Efforts to support families are mainly event based or once off. We lack the courage to speak of irregular family situations. The complexity of the modern family requires collaborative ministries for pastoral accompaniment and support. We recommend action for the possibility of our parishes to become hubs for family support such as counselling, after school programmes to keep children in irregular family situations away from questionable company, grief and survivor groups. More initiative must be made towards regular visits to families who are going through difficult moments.',
            1 => 'Our consensus for CIS-2 is that we agreed that Catholic families in general are losing their way in maintaining and defending their identity as a catholic family. The counter identity which has become a regular occupant in mainstream media, social media and the online gaming world poses a threat to the nurturing minds of our young and sometimes even old. The institutional church needs to create a more dialogue-able approach when preaching on related matters. The neighbourhood church (BEC) needs to be supportive to each other. But for a more sustainable effort, the mission requires a full time and dedicated person to accompany families in irregular circumstances. The dioceses and parishes need to redefine their monetary priority to provide for just wages to bring on board people for the mission.',
            2 => 'We agreed collectively that more learning is required among our church\'s leadership. There needs to be more understanding of the current realities, more understanding of the culture and traditions in sabah, sarawak and semenanjung. Perhaps some clergy should be working beyond the parish or diocese boundaries such as in NGOs, schools, community centres in order to be relevant and to be first responders. As a church we need to overhaul our way of reaching out. Regardless if we are planning to be the first responders to the specialised help, we must be trained, qualified and certified for the mission. Much of our services in church are executed by caring and dedicated people who fall into burnt out or into blunder simply because they were not trained for the mission. We need to relook at our priorities and spend money for the mission.',
            3 => 'We unanimously agree that it is time for more young people to take up leadership in our parishes and dioceses. Young people around the ages of 25 to 45 have better insights and are exposed to newer ways of managing and executing plans. The ever fast moving world we live in does not stop for the older generation to catch up and so the younger generation have an upper hand in responding quickly. That being said, we do not recommend removing the older generation from the equation but to redefine roles, so that both the young generation and the older generation may work together in tandem and not in competition for the greater glory of the mission.',
            4 => 'As a group we divided on opposing points in regards to listening to the voices of the "excluded". A strong minority of the group believes that we need to be talking about what is regarded as taboo such as LGBTQIA+, alcoholism, rape, sex, bullying and even improper sexual behaviours. Giving people an outlet to name their demons, and a language to articulate themself is both ways towards justice and healing. Our church communities should be safe havens for victims and survivors not only for their benefit but also for the benefit of others who are being groomed, abused or suffering in any kind of way. The opposing concern in the group is that this may lead to the questioning of ingenuity and integrity of the victims and survivors. This may lead them into another conflict and crisis. We as a group however do agree that we as a church need to send more priests, religious and laity to training and studies on this matter before establishing a mission or commission.',
            5 => 'Our group is unanimous in our discernment that if we want a whole sustainable plan for the mission of the church, we need more employed lay workers. The only hold back that this group recognizes is funding. We propose that we must deep dive into the cost and funding that goes into our clergy, existing employed workforce, pastoral and otherwise. Are we holding back to satisfy and accommodate our selfish desires and to stay within our comfort zone?',
            6 => 'As a group we have discerned that the job description of the parish priest must be listed out in detail and made available to the public. The role of the parish priest can not and should be vague nor up to each parish priest to decide for himself. In order to uphold the integrity of the priesthood and the transparency and accountability of the priest, priests too must be reviewed formally, of their work by the bishop and the parish community. Methods and processes driven by each parish priest must also be evaluated to identify if synodality is resisted or incorporated in their ministry. Our group does present this consensus as an attack on the clergy but as a safeguarding measure against some bad apples in the bunch.',
            7 => 'Mendengar Dan kurang cepat menilai.
            Start from each family.. later invite other group to join the prayer group. 
            Sharing with other people in our area and support each other in prayer.',
            8 => 'Saya ingin meningkatkan kemahiran mendengar dan memahami keluarga tanpa mudah menghakimi. Melalui aktiviti seperti menghadiri Misa bersama keluarga, perjumpaan keluarga, Kumpulan Rosary dan Home Cell Group, kita dapat saling mendengar, mengeratkan hubungan, serta menyebarkan kasih Kristus dalam keluarga.',
            9 => 'There\'s a great need to build families of love and prayer where understanding, respects and forgiveness is practiced and uphold.',
            10 => 'Our consensus is:- taking the initiatives to listen more than to speak or judge',
            11 => 'Improve spiritual life and not 100% about obligations. Listen, share and talk to each other. 

            1. Equipping yourself by improving your own spiritual life. Be ready to face the real situations outside. 
            2. Listening to the holy spirit, go out and do something different.
            3. Improve yourselves before the family. 
            4. Slowly, things will change. Never force someone to drastically change.
            5. Parental is the most important role in improving one\'s spiritual life.',
            12 => 'Healing and reconciliation starts from within own church community, only then the community can reach out to others who have yet to learn about healing and reconciliation.',
            13 => '1. Encourage family members to take part in Church activities.
            2. Make a channel for family members to share important massages to build a strong faith in the family.',
            14 => 'Priests to show more compassion to dysfunctional families, to guide and help instead of judge; Church to guide families to pray together and come to church as one family; Church to provide guidance or training on how to reach out and help families in difficulties.',
            15 => 'Self-improvement of all individuals is needed to assist the families and the church. Individuals in the families and individuals in the church, play a collective effort to improve themselves. Once one self is improving, everything around that person will improve including the families and churches. From there, we will have a strong foundation to ensure the continuity of the families and the church as the instruments of healing and reconciliation.',
            16 => 'Listening skill in the family is needed so that each members of the family do not quick to jugement. Means that is it begins with ourself, then to others throught the cells group family gathering and prayer group.',
            17 => '1. Healing and reconciliation among families and church members. To be able to work with one another and not be judgmental.
            2. Need to reach out to others outside church ministries and to journey with them through church groups e.g, lay counseling, family ministries, ministry of the poor, etc',
            18 => '1. Church could be the instrument of recociliation by reaching out beyond our respective churches. This is because healing is not just for us Catholics but also for other communities around us including the protestants, non-Christians and also the non-believers. So when the  church of Makaysia serves without conditions, we become the true instrument of reconciliation.',
            19 => '-Expose umat to healing seminar (LSS).
            - Continuos faith formation, have a support group in the church to reach out to everyone especially after seminar.
            -Train lay counselor to help family in crisis
            - Be a witness to others, accept everyone without judging.',
            20 => 'There is a need to reach out to individual families within the church to listen to their needs and struggles. The church must show that it is a caring church and this can only come about through a strengthening of one\'s spiritual life beyond what we are obligated to do through the wisdom of our foreparents.',
            21 => 'Fundamentally, head of the family plays an important roles in the part of religious belief. Educate, lead n communicate with the family members to join the church. Join church activities in order to persue and involve more willingly in their obligations. The foremost is to learn to outreach others to spread the good news of God. To care one another in God\'s way. Communication is important too, in family, in church, in society and go deeper to the remote areas to bring the lost sheep out to our Lord.',
            22 => 'Exposure to the faith formation and others healing programmes.
            Reaching out to others members, not just within close community.
            Introduce a counsellor as to counselling any matter.',
            23 => '1. Parent with uncertainties cannot bring the family in good and always broken.
            2. Spirituality that the family have can improve the well being and show great love to the children.
            3. The church play important role to build strong family lives and encourage them to come to church.
            4. Obligation commitment in the family is vital.
            5. Faith formation can help family improve to inspire to encourage love within oneself.',
            24 => 'Accepting those Catholics who had converted to Islam, and now desires to return to the Church. A mechanism need to be introduced to bring them back into full communion with the Church.',
            25 => 'Personal healing - forgiveness, among family members (misunderstanding, fighting, problem) husband and wife and children. Talk to each other, lunch, dinner together. 
            Ministry in church, counseling like mental health program to help broken family, guiding them. 
            Spiritual strength, prayer and receiving Sacrament in the church (not depend online mass)',
            26 => 'To be able to seek help privately and using new ways via technology. This can help the church to identify those who have problems that may be shy to reach out publicly. To have a familiar face to reach out to make families more comfortable in sharing the problems. This can be from the home cell groups etc. To include more information in seminars and preparation courses to include life experiences and how to expect the kind of problem families should expect and how to deal with them. To have healing sessions in seminars and couples sharing.',
            27 => 'The church plans be short terms or long terms outreach programmes to meet the target groups. PPC together with councillors, lay movements and volunteers are to play their various role eg. RCIA, CCD Sunday, Confirmation class etc. Reports are to be made known to church leaders for improvements. The leaders are to closely monitor the activities and to make sure that it really benefits the target groups.',
            28 => '1. Improvement of planning and activities for the faith formation programme that can be applied in the family.
            2. Improvement of support system from the church (faith formation person in charge/ advisor) to ensure the effectiveness of point 1.
            3. Review regularly the effectiveness of the support system, program of the church and faith formation program of the families.',
            29 => 'Pray
            1. Mencari kekurang diri sendiri, keluarga dan bagaimana mengatasinya dengan mendapat pendangan dari rakan terdekat.
            2. Ketua keluarga harus menjadi contoh kepada ahli dan mendapat pandangan dari HCG kita tentang perkara yang hari dibuat dalam keluarga katolik.
            3Mengambil berat tentang kebajikan keluarga Kristian jiran setempat kita dan menghulurkan bantuan kepada mereka yang memerlukan.
            4.Look into ourself, what is not good and get help from our councilor.Attend seminar and other courses that are related.Like merriage course.
            5. Give private talk to problematic family.And share good example to them.',
            30 => 'Church to provide Counseling and expertise assistance team to the needy especially the broken family and couples who has misunderstanding in long periods. 
            Building of faith via physical church, Mass, Sunday School rather than online. 
            Get Parents to be the first witness in their family, thus building up their faith and responsibility to their family, able to handle healing and reconciliation.',
            31 => 'Families to pray and attend the church together, church to organise outreach programmes for families (be it families in need or not) training programme for church members on how to  help or approach families in need, and the priest to be open and compassionate especially during the homilies in order to attract more families coming to the church.',
            32 => 'Families and individuals ( catholics and non catholics) facing exclusion need personal approach that comes from the role of every catholics. Proper direction to undergo the process would be to seek , to welcome( welcoming back) and to journey with those identified in order to feel the sense of belonging. Hence, the right guidance and formation can augment the role of every catholics in the  process of listening, communicating, and providing accompaniment.',
            33 => 'Families in the community (for example: Cell Group) should find a close, known and trusted individual to explore together if there is a problem. Counseling methods (face-to-face or online) can be used in addition to attending family-related seminars organized by the church. This method should be easy access either private or face to face after they agreed to meet. Pre-marriage courses should implement this element as a guide and action that can be chosen. Young couples should know the action to be taken so they will not do the wrong way.',
            34 => 'Diversity of background and ministries , Leadership and guided by the Holy Spirit, improvement of spiritual life,Establish the community. Participants from home and families .Faith Family Formation.',
            35 => 'The need for improvement to assist the families and Church of Malaysia, to be instruments of healing and reconciliation.
            1. Faith Formation.
            2. Three Pillars i.e. Word of God, Liturgy and living in small community in communion, participating in the Mission of Christ.
            3. Communion, Participation and Mission.
            4. Personal Relationship with God, walking in small community in communion with the bigger church community, participating in the Mission that christ has entrusted to us through our Baptismal promises.
            5. Live out in our reality in our daily life, our Catholic teaching. Be the light of the world and salt of the Church, with the Help of the Holy Spirit.
            6. Need the help of the Holy Spirit.',
            36 => '1. Need ppl from diverse background to involve in the church ministry for us to understand each other culture, lifestyle, etc. to help the families better.
            2. Hierarchy of leaderships need to be by priests and religious to prevent the church from being just like a normal corporation in the world. 
            3. We need to be guided by the Holy Spirit through improvement of spiritual life both community and family.
            4. Establishment of smaller communities so that family can feel part of the big community in Church.
            5. Participation in church ministry starting from home by families\' encouragement.
            6. We need to have faith formation initiative in the church.',
            37 => '1. Parents are the first witness of Christ in the family. Show how forgiveness can built greater family. To solve obstacle of forgiveness is through holy Spirit. 
            2. Reconciliation is truly good to help up mental health, issues that may have built up among family member with the surrounding community. 
            Tuhan memberkati semua.',
            38 => '1.  To provide friendly and accessible access to professionals and experts in mental health and forensic psychology in line with our faith
            2.  Provide professional counsellors 
            3.  Instill the importance of the sacraments in catechism classes
            4.  To train catechism teachers professionally',
            39 => 'Accepting back those Catholics who had converted to Islam, and now desires to return to the Church. We need to find a mechanism to bring them back into full communion with the Church.',
            40 => 'Where do i feel the need
            1. Diversity of bacground in ministry heads
            2. Hierarchy of Leadership need to be by religious -priests, nuns
            3. Both church n families need to guided by the HS in making improvement of their spiritual life
            4. Establishment of smaller communities that work together with the bigger church 
            5. Participation begins from home and families
            5. Purposeful faith formation initiatives',
            41 => '1.Premarriage course need to include counseling session and highlight the importance of psychospiritual integration. 
            2. Counseling service must be easily access through digital counseling as not everyone free to go for physical counseling. 
            3.The eldest as role model to their family members and community around them. 
            4. Organise Family Healing seminar. 
            5. Home visitation and assist them with any assistance that cause problems in their family. 
            6. Faith Formation need to highlight the importance of healing and God as wounded healer.',
            42 => 'To better assist families and the Church in Malaysia as an instrument of healing and reconciliation, greater collaboration and faith formation are needed. The Church should encourage cooperation between its leadership and people from diverse backgrounds and expertise, always guided by the Holy Spirit. Priests and religious brothers and sisters should continue to provide spiritual guidance. While the faithful may contribute professional knowledge and skills, we should respect the leadership and vocation of priests and religious, who have consecrated their lives to God. Healing and reconciliation ministries should be carried out through prayerful discernment and collaboration among different Church institutions. Church organizations should be approachable and provide a safe, welcoming environment where the faithful feel comfortable seeking help and support. Greater unity and participation in both small groups and the wider Church community should be encouraged, with families actively involved in Church life. Finally, ongoing faith formation for clergy, religious, and laity is essential so that all may grow together and become effective instruments of Christ\'s healing, reconciliation, and love.',
            43 => 'We acknowledge that the families are the pillar for the Church and society. Thus, the Church the provide a support system such as well equipped commissions of pastoral care for the families, reaching out to the family in need. The church should continue to be the channel to link the families that need to help and guidance. The Church of Malaysia should continue to provide enough Formation to the families such as Family Seminar, Healing session, Faith Formation, Accompaniment. Encourage all the commissions in the Parish to be involved in the Family Life.',
            44 => '1.Deep relationship with God

            2.outreaching more effectively to smaller communities/ BEC groups by residential area/ make them part of the church/ encourage them to join ministry/ home cell group , easier for people with difficulties to share their problems

            3.formation for families of all generations, importance of faith

            4.godparents play an important role in following up / supporting 

            5.be more caring/ welcoming approach to the marginalised/complicated relationship/broken families',
            45 => 'Every family has experienced misunderstandings, problems, struggles, bitterness and stress. All these things had happened due to lack of faith, lack of knowledge and skills and past emotional wounds. In order to assist the family members they need to embrace total forgiveness. Healing only takes place once they are able to forgive and show charity towards each other. Another essential way is to encourage them to receive the sacraments more actively in the church.',
            46 => '1. Deep Relationship with God. Help the family to build a firm faith.
            2. Formation for all generation.
            3. God parent responsibility.
            4. Outreach in small community such as BEC.
            5. Welcoming all family members especially the broken family etc.',
            47 => '1. Prayer is the most important
            2. To church together
            3.  outreach programme',
            48 => '1) Important to have a deep relationship with God to remain constant in our faith. 

            2) Formation program for families of all generation so that they can be guided in living a Catholic life. 

            3) Godparents play very important role in guiding the godchildren through their faith formation.

            4) Outreach in the smaller communities to provide support. 

            5) Church to have a more welcoming approach for those who are in complicated relationships, broken marriages, marginalized & perhaps a new ministry to cater support to this group.',
            49 => '1. Church need to be more accomadating with new ideas and approach in faith format formation.

            2. Proper training for Faith Formators.

            3. Church emphasize on pastoral care especially to new parents and neophyts.',
            50 => '1. Church need to provide Faith Formation as it is essential in the current generation, especially Gen Z as more and more of them coming back to the church. 
            2. Church have to address formaters or cathecist with proper training to teach about faith. 
            3. Church need to be more accomodating, give extra time to those who in need of classes. 
            4. Church also need to accept those who have long gone from the church, through confession and so on for, while still we need to be sensitive to the country\'s law. 
            5. Church also need to provide pastoral care for families that has trouble.

            crucial point: Church needs to take an action on how to show more care and love for the family members in terms of formation and classes.',
            51 => 'Back to the basic - Faith formation which reflects unto holy family of Jesus, Mary, and Joseph. Faithful to  the catholic teachings.
            Family as 1st place to build relationships based on love, forgiveness, and muture understanding.
            Church in general need to  become real icon for our faith, be the light for all.',
            52 => 'To better assist families and the Church in Malaysia as an instrument of healing and reconciliation, greater collaboration and faith formation are needed. The Church should encourage cooperation between its leadership and people from diverse backgrounds and expertise, always guided by the Holy Spirit. Priests and religious brothers and sisters should continue to provide spiritual guidance. While the faithful may contribute professional knowledge and skills, we should respect the leadership and vocation of priests and religious, who have consecrated their lives to God. Healing and reconciliation ministries should be carried out through prayerful discernment and collaboration among different Church institutions. Church organizations should be approachable and provide a safe, welcoming environment where the faithful feel comfortable seeking help and support. Greater unity and participation in both small groups and the wider Church community should be encouraged, with families actively involved in Church life. Finally, ongoing faith formation for clergy, religious, and laity is essential so that all may grow together and become effective instruments of Christ\'s healing, reconciliation, and love.',
            53 => '1. Parents to educate children of healing and reconciliation
            2.Young generation making excuse not ready to assist the church
            3. Roles of godparents in the community 
            4.',
            54 => '1. Deeper relationship with God
            2. Ongoing formation for families (from children to parents)
            3. Importance of accompaniment for the newly baptised and Godparents to be more aware of their responsibilities
            4. To revamp our BECs or small church communities in their outreach or pastoral work
            5. Church to be more welcoming especially the marginalised and those in complicated relationships',
            55 => 'Thee need to improve our service to families and the Church of Malaysia so that they can continue to be instruments of healing and reconciliation. Families are important because they are role models for the Church. Their faith and example can inspire other families outside the Church to return and grow closer to God. A strong family foundation is the first step to building a strong Church.Children, youth, and the wider community must be guided to have a strong faith and clear understanding of God’s teaching. The Church has a responsibility to carry out its mission by reaching out to families who feel neglected or distant. Through teaching, faith, and love, the Church can bring them back into communion.Discipline is also needed. Families who live with discipline and faith become living examples of what it means to be a Church rooted in Christ. By showing this witness, they encourage others to follow. In this way, the Church in Malaysia can continue its mission of healing, reconciliation, and unity, with families at the center of its strength and growth.',
            56 => 'Family as role model to assist other family in need. Parent responsible to earn good foundation in faith, like going for seminar or involve in ministries in the church. Family should go out and be the disciples to bring good news to others. "I" have to be the 3rd party to "interfere" by helping the family in crisis, to listen to them, to unite them in prayer because most family tend to listen to 3rd party.',
            57 => '1. Faith Formation is the basic foundation for the church to equip the families towards healing & reconciliation.
            2. Church community have to adapt to the new approach in this generation.
            3. Proper training for the cathecist/faith formater so that they will equip with the social challanges faced by the families nowadays.
            4. Back to basic. Faith formation programmes such as Sunday School, RCIA play an important role.
            5.Church must more accomodating, changes for people to speak.
            6. Pastoral Care is important fot the parish community support',
            58 => 'The need to improve our service to families and the Church of Malaysia so that they can continue to be instruments of healing and reconciliation. Families are important because they are role models for the Church. Their faith and example can inspire other families outside the Church to return and grow closer to God. A strong family foundation is the first step to building a strong Church.Children, youth, and the wider community must be guided to have a strong faith and clear understanding of God’s teaching. The Church has a responsibility to carry out its mission by reaching out to families who feel neglected or distant. Through teaching, faith, and love, the Church can bring them back into communion.Discipline is also needed. Families who live with discipline and faith become living examples of what it means to be a Church rooted in Christ. By showing this witness, they encourage others to follow. In this way, the Church in Malaysia can continue its mi',
            59 => 'Bible knowledge penting utk menjadi dasar iman keluarga - keluarga yg aktif menjangkau keluarga yg kurang penglibatan dalam latihan iman harian. 

            Badan Gereja induk perlu mejangkau kepada umat yang lama tidak "melatih" iman.',
            60 => 'Group 11 

            The family unit is the domestic church and family units form the church. 

            We are the body of Christ and therefore we are the church. 

            We must organise & promote the following to help strengthen the families to be instruments of Healing & Reconciliation: 

            1. Marriage Preparation Courses (MPC) & Marriage Enrichment Experience (MEE)
            2. Parenting Courses 
            3. Bible Studies & seminars like LSS & Healing of Families (HOF), etc 
            4. Encourage all families to join at least one church ministry 

            We need to encourage families to have family prayers. 

            Families should learn to show appreciation and gratitude to God and to each other. They should learn to say sorry and to forgive each other.',
            61 => 'Every family shall have the responsibility to spread the good news. To strengthen the family tie especially the younger groups,the church prayer leaders and the God parents should play the good role to unite the family.',
            62 => 'to be a facilitator and notetaker, we need to prepare in prayer, be attentive, take notes, guide the group so all can answer the question when synthesizing.',
            63 => 'What practical steps can i take as a facilitator and note taker to help me in the CIS?

            To be prepared before we facilitate the CIS on that day by setting up rules in a creative way to allow participants to be more at ease to share during the CIS while maintaining a comfortable group discussion. We as facilitators have to be open minded and listen to promptings of the Holy Spirit.',
            64 => '1) The facilitator to be creative in managing the group for eg to have two timings, i.e. 1 minute and 30 seconds so that they can stop in time. 2) Support the idea of hvg everyone to take note so as to ease the job of the notetaker and so that all can focus and pay attention. 3) To find ways to break the ice and make everyone comfortable so that the CIS would be good and everyone will participate freely.',
            65 => 'As a facilitator and notetaker, we feel that we can prepare ourselves by taking time to read, digesting and praying with the preparatory booklet before going for the CIS. We should spend time to understand our role play to participate actively in the sharing. As a facilitator, we will encourage participants to share and records individually. As a notetaker, i will familiarize myself with the names of the my members and write them on my notepad so to be able to focus to capture the gist of the message of each participant before starting the discussion.',
            66 => 'This is excellent',
            67 => 'Pemerkasaan orang muda bermula dari rumah, dengan ibu bapa memainkan peranan penting dalam membentuk sahsiah, nilai dan tanggungjawab mereka.

            Orang muda memerlukan pembentukan, latihan dan bimbingan yang berterusan untuk membantu mereka berkembang sebagai pemimpin.

            Kita perlu memberikan kepercayaan dan peluang kepada mereka untuk memikul tanggungjawab yang sebenar.

            Bakat, kebolehan dan kreativiti mereka perlu dikenal pasti, dihargai dan dikembangkan melalui pelayanan.

            Kita juga perlu mendengar suara mereka, termasuk pengalaman, cabaran, keperluan dan harapan mereka.

            Libatkan orang muda sejak awal dalam perancangan program dan proses membuat keputusan, bukan hanya sebagai pelaksana.

            Para pemimpin perlu mendampingi dan berjalan bersama mereka, sambil memberi ruang untuk belajar, mencuba dan melakukan kesilapan.

            Orang muda bukan hanya pemimpin Gereja pada masa depan. Mereka adalah sebahagian penting daripada Gereja hari ini.

            Memperkasakan orang muda bermaksud membentuk, mendengar, mempercayai dan melibatkan mereka secara nyata.

            Dengan itu, mereka dapat menjadi orang muda yang proaktif, bertanggungjawab dan berani melayani.',
            68 => '1. Memberi kemahiran melalui bengkel / taklimat dan proses kerja program.*  
            Tujuan: Melengkapkan peserta dengan ilmu dan kemahiran praktikal.

            *2. Mewujudkan persekitaran yang kondusif untuk belajar dan bertumbuh dalam masa.*  
            Tujuan: Supaya proses pembelajaran berjalan dengan lebih efektif.

            *3. Memberi kepercayaan dalam proses membuat keputusan untuk mencapai sasaran yang lebih baik.*  
            Tujuan: Meningkatkan keyakinan dan kualiti hasil kerja.

            *4. (Good Soil) - Kepimpinan utama gereja seperti padri, religious dan MPP memberi kepercayaan dan mandat  ( trust)  kepada belia. 
            (Kelemahan seseorang dapat diatasi melalui persekitaran yang baik.) 
            Contoh: Tanah yang subur akan menghasilkan tanaman yang baik.',
            69 => '1) Penghargaan amat penting bagi membolehkan belia atau orang muda lebih bersemangat. 
            2) Berjalan bersama dengan belia  dalam pelayanan. 
            3)  Mendengar suara orang muda dan beri peluang untuk memberikan idea- idea mereka. 
            4)  Jangan halang belia untuk berkarya asalkan tidak lari dari landasan.
            5)  Menggalakkan penglibatan belia dalam pelayanan gereja dan MPP dengan memberi mereka tanggungjawab.
            6)  Menubuhkan platform bagi mereka untuk menyampaikan idea dan membantu mereka untuk belajar membuat keputusan.
            7) Bimbingan secara berterusan supaya belia tidak keluar daripada tujuan pelayanan sebenar. 
            8) Memberi contoh yang baik kepada belia. Tidak meninggalkan belia dalam apa jua keadaan. 
            9)  Meletakkan Roh Kudus sebagai tunjang dalam apa juga keputusan. 
            10) Hargai talenta belia agar mereka merasa diterima.',
            70 => 'For the young people to come forward to make decision, we have to \'listen\', \'trust\', and give them \'opportunity\'. Listen to the voices of young people without being prejudice. Trust in them that they can also make a wise decision. Last but not least is to give them the opportunity to get involved in the all the decision making process. The church need to give a platform for the young people to be trained as a leader with the guidance and advice from the seniors in church so that they know what they overlook or missed in the decision making process, while at the same time they can make a wise decision for the good of the Church. Lastly, we have to embrace and accept the changes that the young people brings.',
            71 => 'Golongan muda perlu diberi ruang dan peluang untuk terlibat secara aktif dalam kehidupan dan pelayanan Gereja. Mereka perlu didedahkan kepada pelbagai bentuk pelayanan serta diberikan pembentukan kerohanian yang berterusan. Suara, idea dan pandangan golongan muda hendaklah didengar dan dihargai dalam perbincangan serta proses membuat keputusan. Golongan muda juga perlu diberikan kepercayaan untuk merancang, memimpin dan melaksanakan aktiviti yang sesuai dengan keadaan mereka. Pemimpin perlu menjadi pendengar yang baik serta memberikan galakan dan sokongan yang positif kepada golongan muda. Bimbingan daripada pemimpin tetap penting supaya golongan muda dapat berkembang dan bergerak dalam landasan yang sesuai dengan Gereja. Komunikasi yang terbuka antara golongan muda dan pemimpin perlu diperkukuh bagi membina hubungan yang saling memahami. Hala tuju penglibatan golongan muda dalam Gereja perlu jelas dan mengambil kira keperluan serta realiti kehidupan mereka. Golongan muda dan pemimpin perlu saling mendengar, belajar dan melengkapi antara satu sama lain. Dengan pendekatan ini, golongan muda dapat diperkasa sebagai anggota Gereja yang aktif, bertanggungjawab dan turut memainkan peranan penting dalam proses membuat keputusan.',
            72 => 'The Holy Spirit is calling the Church to listen to, accept, and empower young people as active participants in the life and decision-making of the Church. Young people need space to share their ideas, take leadership roles, and be involved in planning and serving in Church ministries. At the same time, they need guidance, encouragement, and accompaniment from older generations and Church leaders. Youth are still growing and learning, so the Church should allow them to learn from their mistakes and discover their identity and responsibility as Catholics. We also need to reach out to young people who are not active in youth groups and create new ways to make Church life and worship more meaningful and engaging. There is still a gap between generations, and sometimes young people may feel unappreciated or unheard. Therefore, both generations must learn to listen, respect, and journey together. With the guidance of the Holy Spirit, the Church can create a welcoming environment where young people feel valued, trusted, and encouraged to use their gifts in building the Church. At this age group, help them to discern on their vocations in life, e.g. marriage, singlehood, priest, religious etc. The Church elders must be humble in interacting with the young people.',
            73 => 'Soalan 1: Belia perlu dihargai dan diberi penghargaan agar wujud rasa memiliki (sense of belonging) terhadap Gereja. Penghargaan terhadap sumbangan, idea dan usaha mereka dapat membuatkan belia berasa bahawa mereka penting, diperlukan dan mempunyai tempat dalam komuniti Gereja. Pada masa yang sama, pihak Gereja, MPP dan para paderi perlu hadir sebagai pembimbing dan mentor yang sentiasa mendampingi belia. Mereka perlu menunjukkan teladan melalui tindakan (lead by example), mendengar pandangan belia serta membimbing mereka dalam memahami peranan dan tanggungjawab masing-masing. Dengan sokongan dan bimbingan yang berterusan, belia akan lebih yakin untuk terlibat secara aktif dalam kehidupan dan pelayanan Gereja.

            Soalan 2: Belia perlu diberikan ruang dan peluang untuk bersuara serta menyampaikan pandangan dalam paroki masing-masing. Suara dan perspektif belia perlu diambil kira, khususnya dalam perkara yang berkaitan dengan kehidupan serta masa depan Gereja. Oleh itu, penglibatan belia dalam MPP perlu dipertingkatkan agar lebih ramai wakil belia dapat terlibat secara langsung dalam proses perbincangan dan membuat keputusan. Pada masa yang sama, pihak Gereja perlu memberikan kepercayaan dan tanggungjawab kepada belia untuk mencetuskan idea serta membawa perubahan yang positif. Apabila suara mereka didengari dan kepercayaan diberikan, belia akan berasa dihargai, lebih bersemangat dan terdorong untuk terlibat secara aktif dalam Gereja.',
            74 => 'Dalam Memperkasakan Golongan Muda

            1.  Memberi Masa untuk Mendengar  :
            Luangkan masa mendengar idea & pendapat golongan muda.
            
            2.  Memberi Ruang untuk Berkarya:
            Sediakan tempat Dan peluang untuk mereka menganjur program dan melayani.

            3.  Memberi Ruang untuk Bersuara:
            Benarkan mereka menyuarakan pandangan dan terlibat dalam membuat keputusan.

            Rumusan:
            Pemimpin perlu Dengar, Bagi Ruang Berkarya, dan Bagi Ruang Bersuara kepada belia.',
            75 => 'Kumpulan kami mencapai kesekapatan bahawa memperkasakan golongan muda bermaksud memberikan peluang kepada para belia untuk melibatkan diri dan juga menghapuskan jurang perbezaan unur dan pengalaman dalam kertelibatan Membuat keputusan kerana semua suara layak didengar. Satu Solusi daripada ini adalah harusnya ada bimbingan dan pendedahan awal kepada para belia  dan perlu ada sikap Keterbukaan iaitu Saling menerima dan terbuka kepada yang memberi dan menerima. Kami merasakan bahawa Roh Kudus mengajak kita bukan sahaja menumpuhkan membuat program kepada para belia tetapi berjalan bersama dengan para belia.',
            76 => 'The role of parents in nurturing and strengthening the faith of young people is crucial in preparing them to serve in ministry. It also plays an important role in bridging the generational gap and fostering continuity in church leadership.',
            77 => 'The role of parents in nurturing and strengthening the faith of young people is crucial in preparing them to serve in ministry. It also plays an important role in bridging the generational gap and fostering continuity in church leadership.',
            78 => 'Kami sedar ini adalah satu proses dan bersetuju bahawa golongan muda perlu diberikan kepercayaan, ruang untuk bersuara dan tanggungjawab nyata dalam kehidupan dan misi gereja dengan bantuan Roh Kudus.',
            79 => 'Building a Welcoming & Inclusive Parish

            - Create an open and welcoming parish where everyone feels accepted, valued and at home.

            - Prioritise mental well-being, providing a safe and supportive space where people feel heard and cared for.

            - Reach out to those who feel excluded or disconnected and gently accompany them back to the Church through small, personal steps.

            - Build relationships through BECs, Mass and parish activities, sharing upcoming events and connecting people with those they feel comfortable with.

            - Provide counselling and encouragement for those facing personal or family circumstances, including broken or mixed marriages.

            - Ensure continued accompaniment: Whoever introduces someone to a ministry should follow up and help them feel comfortable, supported and part of the community.

            - Above all, build a culture of acceptance, openness and belonging, so that everyone knows there is a place for them in the Church.',
            80 => 'A church that is open, with concrete action. Providing space, listen without judgement with compassionate and understanding',
            81 => 'Group 3 

            Building a Welcoming & Inclusive Parish

            1. Create an open and welcoming parish where everyone feels accepted, valued and at home.

            2. Prioritise mental well-being, providing a safe and supportive space where people feel heard and cared for.

            3. Reach out to those who feel excluded or disconnected and gently accompany them back to the Church through small, personal steps.

            4. Build relationships through BECs, Mass and parish activities, sharing upcoming events and connecting people with those they feel comfortable with.

            5. Provide counselling and encouragement for those facing personal or family circumstances, including broken or mixed marriages.

            6. Ensure continued accompaniment: Whoever introduces someone to a ministry should follow up and help them feel comfortable, supported and part of the community.

            7. Above all, build a culture of acceptance, openness and belonging, so that everyone knows there is a place for them in the Church.',
            82 => 'Each and everyone of us is called to reach out to others. Reaching out to others must begin in our homes where we learn to be in tune or emphatic towards each other. Then, we need to practice the same in the church to reach out to the wider communities or audience. Learning to sacrifice is also part of empathy because we want to know fully the situation of others so that we can reach out to them effectively.',
            83 => '1. Memberi kemahiran melalui bengkel / taklimat dan proses kerja program.*  
            Tujuan: Melengkapkan peserta dengan ilmu dan kemahiran praktikal.

            *2. Mewujudkan persekitaran yang kondusif untuk belajar dan bertumbuh dalam masa.*  
            Tujuan: Supaya proses pembelajaran berjalan dengan lebih efektif.

            *3. Memberi kepercayaan dalam proses membuat keputusan untuk mencapai sasaran yang lebih baik.*  
            Tujuan: Meningkatkan keyakinan dan kualiti hasil kerja.

            *4. (Good soil) - pihak gereja terutama padri,religious dan MPP perlu memberikan peluang dan ruang kepada belia untuk menjadi pemimpin. (Kelemahan seseorang dapat diatasi melalui persekitaran yang baik.) 
            Contoh: Tanah yang subur akan menghasilkan tanaman yang baik.

            ---',
            84 => '*Konsensus yang dipersetujui:*

            1.  *Libatkan dalam struktur ( pastoral)*: Komiti / Komuniti / Kerasulan. Mesti cari orang muda untuk menjadi ahli.
            2.  *Beri Ruang Suara*: Cipta ruang untuk belia memberi pendapat supaya mereka merasa dihargai.
            3.  *Peranan Pemimpin*: Memberi panduan dan bimbingan kepada belia kerana mereka adalah masa depan Gereja.
            4.  *Keputusan Kolektif*: Suara dan keputusan belia diambil kira sebagai pandangan kolektif

            *Keyword: DENGAR - LIBATKAN, HARGAI DAN AMANAH',
            85 => 'We empower young people by giving them a voice, trust, responsibility, and a meaningful space to participate in decision-making.
            The Parish Pastoral Council (PPC) should include at least three young people as members, so that their perspectives and experiences are represented in the decision-making process.
            At the same time, both the young and senior members need to listen to one another, engage in dialogue, accept and understand each other’s ideas, perspectives, and feelings. Through mutual listening, respect, and openness, both generations can walk together, discern together, and work together as one body in the Church.',
            86 => 'Our group has agreed that the church rejoices in having new catechumens, however...',
            87 => "Doable, realistic and evangelising church. To be evangelised and to evangelise through inclusivity and bridge building. To know Jesus, to love Jesus and to share Jesus. ",
            88 => "Our dream is for a One Malaysia church, not only in name but in being. A church that is humble and open to renewal, changes, accepting, inclusive, able to listen to one another, while accepting each others uniqueness and diversity; heading towards one common Christ centered goal. ",
            89 => "
            To be Fruitful, producing tangible action plans focusing on unity, and guided by the Holy Spirit. Unity also means pastoring to the marginalised.
            
            On an individual level, we should represent those who would not be present in MPC, bringing the voice of the voiceless. 
            
            As a Malaysian church, we should look into the specific ministries for the marginalised (e.g. differently abled, indigineous) not just in outreaching missions, but including them in the parish community (Church life) and sacramental life.",
            90 => "We dream with expectant HOPE, that the Spirit will continue to speak and empower all God’s people, especially the young. May we be encouraged to listen to the Spirit working in each other and participate boldly together with a sense of newness of life for the journey ahead. ",
            91 => "
                To move in synodality as one, united in the new way of being church, openness of heart and acceptance (adaptation) of our differences of practice, through acceptance and commonality with each other. 
                
                To have a standardised format or practice with consistency in Malaysia. For example, the RCIA syllabus & the confirmation age across Peninsular, Sabah & Sarawak. ",
            92 => "A conversion of hearts is required that effectively impacts relationships. Conversion encourages us to reach out to the ones on the peripheries ensuring everyone is seen and all voices heard - the ones that are hurt, have left the church, divorced etc. We are called to be aware and reach out creating a safe environment - without judgement, with respect and uphold human dignity. We as the people of God are called to listen to every voice, allowing the Holy Spirit to speak out to all. ",
            93 => "We wanna see a renewed church and the renewal can only happen if we ourselves believe and trust in the spirit though there may be resistance coming from ourselves or from others. We just have to believe cause a dream is not what you see in sleep dream is something which does not let you sleep ",
            94 => "To cultivate unity in diversity: Diversity in language, culture, ministry and personal experience. To humbly and with an open heart share our joys and sorrows with the other dioceses, where are joys could be the answer to their sorrows and vise versa. At the end, we dream to bring home the hope that labels are self-imposed and we are truly a one united Church of Malaysia. ",
            95 => "Our dream is that MPC 2026 will be a place where we commit to both personal and collective transformation, which leads to inclusivity at different levels, where when the decisions are made, the grassroots are also included in the transformation journey. Part of the inclusion is to also to intentionally invite the marginalised (e.g.: migrants, orang asli) on the journey. Finally, we hope MPC 2026 will strengthen collaboration and networking across the three regions, creating greater opportunities to share resources, experiences and gifts, and to journey together towards a more inclusive and synodal Church. ",
            96 => "
                Our group 16,dream in MPC 2026 is to see an enhancement of our spiritual faith,acknowledge our weaknesses in Semanjung and hope for an uplift of ongoing channels of communication via technology in Sabah and Sarawak in the rural areas.To train and elevate more lay people to assist the pastoral activities. 
                At the same time stay united as one Malaysian Catholic Church.",
            97 => "What dreams do I have for the MPC2026:
                
                A conversion of heart to be able to contemplate the will of God and put it into action through the conversations in the Spirit at the MPC2026.",
            98 => "The common dream for MPC is for a more inclusive, trusting and intergenerational Church, where everyone is genuinely welcomed, heard and given opportunities to contribute. We agreed that discernment plays an important role in listening  to one another and to the Holy Spirit to understand where the Church is being called. This means recognising the strengths and contributions of every generation. From discernment comes acceptance: accepting people as they are, welcoming newcomers without judgement, and creating spaces where people feel trusted and able to participate meaningfully. We also need to discern whether our words and actions are bringing people closer to Christ or away from Him. Then comes change, having the courage to respond to what we have discerned rather than returning to our parishes and continuing as before. This change should include breaking down silo mentality, strengthening intergenerational collaboration and creating equal opportunities for young people. Ultimately, our dream is that the spirit of this gathering does not end here, but is brought back to our parishes and translated into action. We hope to become faithful and strong witnesses, remaining open to where the Holy Spirit leads us. ",
            99 => "Our group hopes for a Unity and oneness in the church of Malaysia. We need to have humility to show dignity and respect towards all our church brethren. We must be united in living our Faith (being, not just doing), not to just jump into making big changes to our Faith, but starting with the smaller things in our Faith life that can change (extends into community life as well). All group members agreed that Father Dominic's input on the reason we're going to MPC, which is to learn from Christ through the encounters & discussions we have at Sibu. We wish to be united in our Faith and Church life following the words of Pope Leo XIV, In Him (Christ), we are one. ",
            100 => "
                Our dream for the MPC 2026 is a call to bring the voices of our parishes into one community of the Catholic Church in Malaysia. This invitation is not just an event, but a summons us to hear God’s voice, to empty ourselves, and to do His will.  
                
                We may be from different backgrounds, yet together we are invited to bring one voice, a voice rooted in Christ, transcending boundaries of race, culture, and ministry. 
                
                Our participation is both a privilege and a responsibility. By treating this invitation seriously, we carry the hopes of our parish and return with impact for the next generation of leadership and community.  
                
                The dream of unity is already present; God desires that we encounter it, walk together, and keep dreaming until it becomes reality.
                
                This journey challenges us to move beyond individual dreams, for only a common dream allows us to climb the mountain together. It is a dream of a Church renewed in faith, strengthened in families, guided in society, and caring for creation.  
                
                As we walk together, we echo John 17:21: “That they may be one”. The dream is a journey, and one day, by God’s grace, it will be realised. Let us walk together in humility, allowing the Holy Spirit to guide us and fulfil His will in His time.",
            101 => "Group 14 Our dream is for a united, synodal Church: one voice, one mission and one direction, where every generation across the three regions of Malaysia, especially the young, is heard and valued. (1) With hope, we discern with the Holy Spirit, embrace every voice, and journey together in understanding, acceptance and love. (2) ",
            102 => "In the process of MPC event, we need to continue to pray for the virtue of patience and be open minded, humble and brave enough to accept the new pastoral decision for the betterment of our own personal spiritual growth and the communial journey as the people of God. ",
            103 => "Our collective dream in consensus is to see the emergence of a Christ-centric MPC community which will be guided by the spirit of God in it's efforts to formulate initiatives and strategies towards the continued growth of His Kingdom and the strengthening of faith of His people. 
                We pray and hope that the MPC participants will focus on Christ despite their differing opinions and thoughts, creating a sacred and peaceful platform of discussion with mutual respect and in humility. 
                We also pray that true community renewal will take place with conversion of hearts.",
            104 => "Our group is so glad that all nine dioceses are coming together with Jesus right at the center of everything we do. For us, being Church and getting back to basics simply means living like the early Christians. It means staying close to the Mass, reading the Bible, connecting in our BECs, and reaching out. Even though West and East Malaysia are quite different, that is actually our strength! We celebrate Peninsular Malaysia’s multicultural setup and East Malaysia’s warm, Kampung -style roots. When we bring together the West’s organizing strength and the East’s genuine warmth and simplicity, we show the rest of Malaysia what true unity looks like. At the end, we also not forgetting the mission of each one of us : Go and spread the good news.",
        ];

        $conversations=Conversation::with("conversationGroups")->get();
        $n=count($conversations);
        $m=count($consensus_texts);
        $per_conversation=floor($m/$n);

        

        foreach($conversations as $index=>$conversation){
            //split consensus evenly
            
            foreach(array_slice($consensus_texts,$index*$per_conversation,$per_conversation) as $indexC=>$consensus_text){
                $responses[]=$conversation->conversationGroups[$indexC]->responses()->create([
                    "consensus_text"=>$consensus_text
                ]);
            }
        }
    }
}
