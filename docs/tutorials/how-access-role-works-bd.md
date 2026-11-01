# স্টাফ অ্যাক্সেস কীভাবে কাজ করে: Permissions, access role আর staff access

এই টিউটোরিয়ালে ব্যাখ্যা করা হয়েছে, ড্যাশবোর্ড কীভাবে ঠিক করে একজন স্টাফ কী খুলতে পারবে আর কী
করতে পারবে, আর এটা নিয়ন্ত্রণ করে যে তিনটা স্ক্রিন, সেগুলো কীভাবে একসাথে কাজ করে:

| স্ক্রিন | কোথায় | প্লাগইন | কী সেট করে |
|---|---|---|---|
| **Permissions** | Staff & access → Permissions | Free | প্রতিটা **WordPress staff role**-এর জন্য একটা নিয়মের সেট: *Manager* আর *Staff* |
| **Access roles** | Staff & access → Access roles | Pro | নাম দেওয়া কাজের প্রোফাইল (*Receptionist*, *Accountant*…), যা মানুষকে দেওয়া যায় |
| **Staff access** | Staff & access → Staff access | Pro | প্রতি জনের জন্য: কোন access role আছে, ব্যক্তিগত override, ব্যক্তিগত PIN |

এগুলো **স্তর (layer)**। প্রতিটা স্তর নিচের স্তরের সিদ্ধান্ত বদলাতে পারে, দুই দিকেই (খুলেও দিতে পারে,
বন্ধও করতে পারে)। বেশিরভাগ হোটেলের প্রথম একটা বা দুটো স্তরই যথেষ্ট।

নিয়মগুলো নিজে লেখা আছে [`docs/modules/M13-staff-permissions.md`](../modules/M13-staff-permissions.md)
আর ADR-004-এ। এই পাতা আর spec-এর মধ্যে অমিল থাকলে spec-টাই ঠিক, আর কোডে bug আছে।

> শব্দ: **access key** = একটা পাতা বা কাজের নাম, যেটা অনুমতি দেওয়া বা আটকানো যায়,
> **level** = সেই key-এর অবস্থা (*Open* / *Passcode* / *Locked*), **override** = শুধু একজন মানুষের
> জন্য একটা key-এর আলাদা level, **default** = কেউ কিছু সেট না করলে যা প্রযোজ্য হয়।

---

## ১. শব্দগুলো

### Access key: যা অনুমতি দেওয়া বা আটকানো যায়

প্রতিটা পাতা আর প্রতিটা সংবেদনশীল কাজের একটা **access key** আছে:

- পাতা: `page.bookings`, `page.rates`, `page.settings`, `page.payroll`…
- কাজ: `bookings.cancel`, `payments.void`, `guests.ban`, `rooms.manage`…

Free প্লাগইন তার key-গুলো রেজিস্টার করে `includes/Access/AccessRegistry.php`-এ। Pro আর client
add-on তাদের key যোগ করে `rtbp_access_keys` filter দিয়ে। প্রতিটা key-এর একটা **default level** আছে:
ফ্রন্ট ডেস্কের বেশিরভাগ key-এর default *Open*, আর সংবেদনশীলগুলো, যেমন `page.rates`, `page.exports`
আর `page.settings`, এদের default *Locked*।

### Level: কেউ চেষ্টা করলে কী হয়

| Level | মানুষটা কী দেখে | প্লাগইন |
|---|---|---|
| **Open** | কাজ হয়ে যায়। | Free |
| **Passcode** | আগে একটা PIN চায় (তার নিজের ব্যক্তিগত PIN, অথবা হোটেলের fallback PIN)। | Pro |
| **Locked** | মেনু আইটেম বা বাটন লুকানো থাকে, আর কেউ সরাসরি API কল করলেও সার্ভার রিকোয়েস্ট ফিরিয়ে দেয়। | Free |

Pro ছাড়া শুধু *Open* আর *Locked* থাকে। চেক সবসময় হয় **সার্ভারে**; বাটন লুকানোটা শুধু
সুবিধার জন্য।

### "Role" নামে দুটো আলাদা জিনিস আছে

বেশিরভাগ বিভ্রান্তি এখান থেকেই আসে:

| | **WordPress role** | **Access role** (Pro) |
|---|---|---|
| উদাহরণ | *Manager*, *Staff* (আরও আছে *Administrator*, *Hotel Guest*) | *Receptionist*, *Night receptionist*, *Supervisor*, *Housekeeping*, *Accountant*, *Manager* |
| এর মানে | **এই মানুষটা আদৌ ড্যাশবোর্ডে সাইন ইন করতে পারবে কি না**, আর তার মূল (baseline) নিয়ম | **সে কোন কাজ করে**: baseline-এর উপরে আরও সূক্ষ্ম একটা নিয়মের সেট |
| কোথায় সেট হয় | WordPress-এর Users স্ক্রিনে, অথবা staff file লগইন তৈরি করলে নিজে থেকেই | Staff & access → Staff access → *Access role*-এ, অথবা staff file তৈরির সময় |
| এর নিয়ম কোথায় বদলানো হয় | Staff & access → **Permissions** | Staff & access → **Access roles** |
| একজনের কয়টা | সাধারণত একটা | সর্বোচ্চ একটা, অথবা একটাও না |

তাই **Access roles পাতায় "Staff" ইচ্ছা করেই নেই**। *Staff* হলো একটা WordPress role, আর এর নিয়ম
থাকে **Permissions** পাতায়। Access roles পাতায় শুধু Pro-র কাজের প্রোফাইলগুলো থাকে। সেখানকার
*Manager* access role একটা আলাদা built-in role, যার নাম কাকতালীয়ভাবে *Manager* WordPress role-এর মতো;
দুটোর মধ্যে কোনো সংযোগ নেই।

---

## ২. স্তর ১: Permissions (free)

Staff & access → Permissions-এ প্রতিটা WordPress staff role-এর জন্য একটা কলাম থাকে, **Manager** আর
**Staff**, আর প্রতিটা access key-এর জন্য একটা সারি। প্রতিটা ঘরে থাকে *Open* বা *Locked*, আর Pro চালু
থাকলে *Passcode*-ও।

- **Manager**: default হিসেবে সবকিছু *Open*। ম্যানেজার হোটেল চালায়।
- **Staff**: যে key আপনি সেট করেননি, সেটা key-এর নিজের default মেনে চলে (ফ্রন্ট ডেস্ক খোলা; rates,
  exports আর settings বন্ধ)।

এখানে যা সেভ করেন তা জমা থাকে `rtbp_access_settings` → `roleLevels` option-এ, প্রতিটা role আর
key-এর জন্য। শুধু আপনি যে key বদলেছেন সেগুলোই জমা হয়; বাকি সব default মেনে চলতে থাকে।

**একই WordPress role-এর সবাই একই নিয়ম পায়।** Free প্লাগইন এটুকুই দেয়, আর ছোট হোটেলের জন্য
প্রায়ই এটুকুই যথেষ্ট: রিসেপশন টিমকে *Staff*-এ রাখুন, মালিককে *Manager*-এ।

> **Residence TATA-তে এখন:** ইমপোর্ট পুরনো সাইটের স্টাফ নিয়মগুলো *Staff* কলামে কপি করেছে
> (`bookings.create`, `guests.ban`, গেস্ট নোট, `page.availability`, `page.bookings`, `page.guests`,
> `page.rooms`, দুটো রিপোর্ট: সব *Open*)। বাকি key-গুলো তাদের default মেনে চলে।

---

## ৩. স্তর ২: Access roles (Pro)

Staff & access → Access roles-এ নাম দেওয়া কাজের প্রোফাইলগুলোর তালিকা থাকে। Pro প্রথমবার চালু করলে
ছয়টা **built-in** role (*Built-in* চিহ্নিত) ইনস্টল করে:

| Built-in role | সংক্ষেপে |
|---|---|
| **Receptionist** | বুকিং নেয়, গেস্টকে check in আর check out করায়, পেমেন্ট রেকর্ড করে। Rates, exports আর settings বন্ধ। |
| **Night receptionist** | Receptionist-এর মতোই, কিন্তু cancel, refund, লাইন মুছে ফেলা, ban বা rooms report নেই। |
| **Supervisor** | শিফট চালায়: বুকিং cancel আর পরিবর্তন করতে পারে, sales report দেখে, export করতে পারে। রুম আর availability বদলাতে passcode লাগে। |
| **Housekeeping** | ড্যাশবোর্ড, রুম আর availability ছাড়া **সবকিছু বন্ধ**। |
| **Accountant** | পেমেন্ট, রিপোর্ট আর export; পেমেন্ট void বা পরিবর্তন করতে passcode লাগে; বুকিং তৈরি করতে বা গেস্টকে check in/out করাতে পারে না। |
| **Manager** | সবকিছু খোলা, কিন্তু পেমেন্ট void করা, permission ম্যানেজ করা, override রিসেট করা আর activity log আর্কাইভ করতে passcode লাগে। |

Built-in role-গুলো এডিট করা যায় (মুছে ফেলা যায় না), আর **New role** দিয়ে নিজের role বানানো যায়।

Role editor-এ প্রতিটা key-এর জন্য চারটা অপশন থাকে:

- **Default**: role কিছু ঠিক করে না। মানুষটার স্তর ১-এর নিয়ম প্রযোজ্য হয় (Permissions পাতায় তার
  WordPress role-এর কলাম, অথবা key-এর default)।
- **Open**, **Passcode**, **Locked**: role ঠিক করে দেয়, স্তর ১ যা-ই বলুক না কেন।

**Everything else** (জমা থাকে `*` হিসেবে) এক ধাপে সেই সব key সেট করে দেয়, যেগুলো role *Default*-এ
রেখে দিয়েছে। *Housekeeping* এটাই ব্যবহার করে: *Everything else = Locked*, তারপর তিনটা পাতা *Open*।
"সব বন্ধ, অল্প কয়েকটা খোলা" ধরনের role এভাবেই বানানো হয়।

**একটা role বদলালে, যাদের কাছে সেই role আছে তাদের সবার জন্য বদলে যায়**, সাথে সাথেই।

Role-গুলো জমা থাকে `radius_hotel_booking_pro_access_roles` টেবিলে (`levels` হলো key → level-এর
একটা JSON map, *everything else*-এর জন্য `*`)।

---

## ৪. স্তর ৩: Staff access (Pro)

Staff & access → Staff access-এ তাদের সবার তালিকা থাকে যারা ড্যাশবোর্ড ব্যবহার করতে পারে: *Manager*
বা *Staff* WordPress role-এর ইউজার, আর administrator-রা। একজনের পাশে **Manage**-এ ক্লিক করে
সেট করুন:

1. **Access role**: স্তর ২-এর যেকোনো একটা role, অথবা **No access role (defaults)**।
   *No access role* মানে "এই মানুষটার জন্য স্তর ২ কিছুই করে না": সে তার WordPress role-এর জন্য
   Permissions পাতা মেনে চলে (স্তর ১)। **এই কারণেই এখন সবার পাশে default দেখায়**: এখনো কাউকে
   access role দেওয়া হয়নি, তাই সবাই *Staff* কলাম মেনে চলে।
2. **Personal overrides**: একটা key-এর জন্য একটা level, শুধু এই মানুষটার জন্য। প্রতিটা key শুরুতে
   থাকে **Inherit**-এ (কোনো override নেই)। এর পাশে স্ক্রিনে দেখায় **Now: Open · Access role**,
   মানে এই মুহূর্তে কোন level প্রযোজ্য আর সেটা কোন স্তর থেকে আসছে:

   | উৎসের লেবেল | মানে |
   |---|---|
   | *Personal override* | এই মানুষটার উপর সেট করা (স্তর ৩) |
   | *Access role* | তার access role সেট করেছে (স্তর ২) |
   | *Staff role default* | Permissions পাতায় তার WordPress role-এর জন্য সেট করা (স্তর ১) |
   | *Default* | কেউ সেট করেনি; key-এর নিজের default প্রযোজ্য |
   | *Administrator* | সে সাইটের administrator (দেখুন §৫) |

   একটা override **একটা key** বদলায়, কখনো পুরো map না। **Reset overrides** এই মানুষটার সব
   override মুছে দেয়; তালিকার **Reset all overrides** সবার জন্য একই কাজ করে।
3. **Personal PIN**: কোনো কাজ *Passcode*-এ সেট থাকলে এই মানুষটা যে PIN টাইপ করে।
4. **Profile fields**: নিজের staff file-এর কোন ঘরগুলো সে নিজে বদলাতে পারবে।

Override-গুলো জমা থাকে user meta `rtbp_pro_access_overrides`-এ, আর access role থাকে user meta
`rtbp_pro_access_role`-এ।

---

## ৫. শেষ level কীভাবে ঠিক হয়

একজন মানুষ আর একটা key-এর জন্য, যে নিয়ম প্রথমে কিছু বলে, সেটাই জেতে:

```
1. এই key-এর জন্য personal override আছে?          → সেটাই        (Staff access)
2. তার access role এই key সেট করেছে?               → সেটাই        (Access roles)
3. তার access role "Everything else" সেট করেছে?     → সেটাই        (Access roles)
4. Permissions পাতায় তার WordPress role-এর ঘর      → সেটাই        (Permissions)
     (Manager-এর default সবকিছু Open;
      একাধিক staff role থাকলে যেটা বেশি খোলা, সেটা জেতে)
5. key-এর নিজের default                            → সেটাই
6. Administrator আর ফলাফল Locked?                  → Passcode (Pro থাকলে) বা Open (free)
```

এর চারপাশে আরও দুটো নিয়ম আছে:

- **ড্যাশবোর্ডে একদমই অ্যাক্সেস নেই** (*Manager*, *Staff* বা administrator না, যেমন একজন *Hotel
  Guest*): স্ক্রিনে যা-ই থাকুক, সব key *Locked*।
- **Administrator-কে কখনো আটকে দেওয়া যায় না।** যা *Locked* হতো, Pro চালু থাকলে সেটা *Passcode*
  হয়ে যায়, ফলে সে PIN দিয়ে নিশ্চিত করে; Pro ছাড়া *Open*। এটা ইচ্ছাকৃত: Permissions পাতায় একটা
  ভুলের জন্য সাইটের মালিক যেন বাইরে আটকে না যায়।

ধাপ ১ থেকে ৩ চলে শুধু তখনই, যখন Pro চালু **আর** **Advanced staff access** ফিচার চালু থাকে
(Settings → Pro features)। এটা বন্ধ করলে সবাই ধাপ ৪ থেকে ৬-এ ফিরে যায়।

কোডে: free-এর `includes/Access/Access.php`-এর `Access::resolve()` ধাপ ৪ থেকে ৬ করে, আর মাঝখানে
`rtbp_access_level` filter কল করে। Pro-র `UserAccessService::resolve()` সেই filter-এ ধাপ ১ থেকে ৩
দিয়ে উত্তর দেয়।

---

## ৬. একটা উদাহরণ: Adjoua Pokou

Adjoua Pokou-র WordPress role *Staff*, আর কোনো access role নেই।

| Key | Override | Access role | Permissions → Staff | Key-এর default | **ফলাফল** |
|---|---|---|---|---|---|
| `page.bookings` | | নেই | Open | Open | **Open** (*Staff role default*) |
| `bookings.cancel` | | নেই | সেট করা নেই | Open | **Open** (*Default*) |
| `page.rates` | | নেই | সেট করা নেই | Locked | **Locked** (*Default*) |

এবার তাকে **Night receptionist** access role দিন:

| Key | Override | Night receptionist | **ফলাফল** |
|---|---|---|---|
| `page.bookings` | | Open | **Open** (*Access role*) |
| `bookings.cancel` | | Locked | **Locked** (*Access role*): cancel বাটন উধাও হয়ে যায় |
| `page.rates` | | Locked | **Locked** (*Access role*) |

শেষে, নাইট ম্যানেজার চান সে rate plan দেখতে পাক, কিন্তু রাতের শিফটের আর কেউ না: তার Staff access
পাতায় একটা override সেট করুন `page.rates` = *Open*।

| Key | Override | Night receptionist | **ফলাফল** |
|---|---|---|---|
| `page.rates` | Open | Locked | **Open** (*Personal override*) |

রাতের অন্য receptionist-দের উপর কোনো প্রভাব পড়ে না।

---

## ৭. কীভাবে করবেন

**পুরো রিসেপশন টিমকে একই নিয়ম দিতে (free-ই যথেষ্ট)।**
সবাইকে *Staff* WordPress role-এ রাখুন, Staff & access → Permissions-এ *Staff* কলাম সেট করে সেভ
করুন। কাউকে access role দেবেন না।

**আলাদা আলাদা কাজ থাকলে (রিসেপশন, হাউসকিপিং, হিসাব)।**
হোটেলের সাথে মিলিয়ে Staff & access → Access roles-এর built-in role-গুলো এডিট করুন, তারপর Staff & access
→ Staff access-এ প্রত্যেককে তার role দিন। যাদের কোনো role নেই, তাদের জন্য নিরাপদ baseline হিসেবে
Permissions পাতার *Staff* কলাম রেখে দিন।

**কাউকে ম্যানেজার বানাতে।**
হয় তাকে *Manager* WordPress role দিন (WordPress → Users → edit → Role), তাহলে সে *Manager* কলাম
মেনে চলবে; অথবা তাকে *Staff*-এই রাখুন আর *Manager* access role দিন, যেটা সবচেয়ে সংবেদনশীল
কাজগুলোর জন্য PIN-ও চায়। যেকোনো একটা ব্যবহার করুন, দুটো একসাথে না, নইলে কোন নিয়ম প্রযোজ্য তা
বোঝা কঠিন হয়ে যায়।

**একজন মানুষের জন্য একটা ব্যতিক্রম।**
Staff access-এ একটা personal override। কয়েকজনের একই ব্যতিক্রম লাগলে বরং একটা access role বানান:
override সহজেই ভুলে যাওয়া যায়।

**নতুন স্টাফ।**
লগইনসহ staff file তৈরি করলে (Staff & access → Staff → **Add an employee**) *Staff* role-সহ একটা
WordPress ইউজার তৈরি হয়, আর একই সাথে তার access role বেছে নেওয়া যায়।

**কোনো কাজ লুকানোর বদলে PIN দিয়ে সুরক্ষিত করতে।**
Key-টাকে *Passcode*-এ সেট করুন (Permissions-এ, কোনো access role-এ, বা override হিসেবে), আর
Staff access-এ মানুষটাকে একটা ব্যক্তিগত PIN দিন। PIN কতক্ষণ বৈধ থাকবে আর fallback PIN আছে
Settings → Passcodes-এ।

---

## ৮. সমস্যা হলে

**"আমি Permissions পাতা বদলেছি, কিন্তু এই মানুষটা এখনো পাতাটা দেখতে পাচ্ছে।"**
Staff access-এ তাকে খুলুন আর সেই key-এর পাশে উৎসটা পড়ুন। যদি লেখা থাকে *Access role* বা
*Personal override*, তাহলে উপরের একটা স্তর জিতছে; সেখানেই বদলান। যদি লেখা থাকে *Administrator*,
তাহলে সে সাইটের administrator, তাকে আটকানো যায় না।

**"একটা role-এর সবার জন্য একটা পাতা উধাও হয়ে গেছে।"**
কেউ access role-টা এডিট করেছে; সেটা একসাথে সব ধারকের উপর প্রযোজ্য হয়। কে কোন role কখন বদলেছে,
তা Activity log-এ রেকর্ড থাকে।

**"সবার পাশে *No access role (defaults)* দেখাচ্ছে।"**
এটাই শুরুর অবস্থা: কারো access role নেই, তাই সবাই Permissions পাতা মেনে চলে। কোনো সমস্যা নেই।

**"Access roles পাতায় Staff নেই।"**
*Staff* একটা WordPress role, access role না। এর নিয়ম বদলান Staff & access → Permissions-এ (§১)।

**"সেভ করার পর মেনু বদলায়নি।"**
আপনার নিজের মেনু সাথে সাথেই রিফ্রেশ হয়। অন্যরা তাদের পরের রিকোয়েস্টে নতুন নিয়ম পায়, তাই
তাদের হয়তো পাতাটা রিলোড করতে হবে।
