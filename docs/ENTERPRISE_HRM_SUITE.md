# سند جامع ماژول مدیریت منابع انسانی WebinoERP (Enterprise HRM Suite)

این مستند نقشه کامل، مدل داده‌ها، خدمات و ارتباطات بین‌ماژولی ماژول **مدیریت منابع انسانی (HRM)** سامانه **WebinoERP** را تشریح می‌کند.

---

## ۱. نقشه راه و پوشش ۱۶ گانه ماژول HRM

| بخش | عنوان | وضعیت موجود در سورس‌کد | نیازمندی‌های تعمیق و یکپارچگی |
| :---: | :--- | :--- | :--- |
| **۱** | **ساختار سازمانی و چندشرکتی** | `HrmOrgPosition`, `OrgBranch`, `OrgEntity` | درختواره دپارتمان‌ها و اتصال مراکز هزینه به دفتر کل مالی |
| **۲** | **پرونده پرسنلی (Employee Master)** | `HrmEmployee`, `HrmEmployeeProfile`, `HrmDependent`, `HrmPersonnelDocument` | اعتبارسنجی کد ملی، مدیریت انقضای مدارک، و تاریخچه تغییر احکام |
| **۳** | **جذب و استخدام (Recruitment)** | `HrmJobPosting`, `HrmJobApplicant`, `HrmInterview` | مصاحبه‌های چندمرحله‌ای، ترکر متقاضیان و تبدیل خودکار به پرسنل |
| **۴** | **ورود و خروج (On/Offboarding)** | `HrmOnboarding`, `HrmOffboarding`, `Task` | چک‌لیست تحویل تجهیزات، مصاحبه خروج و قطع دسترسی‌های سیستم |
| **۵** | **حضور، غیاب و شیفت کاری** | `HrmAttendanceRecord`, `HrmShiftTemplate`, `HrmShiftAssignment`, `HrmAttendancePunch` | اتصال به **Webino Hardware Gateway** (دستگاه‌های ZKTeco روی پورت ۴۳۷۰) |
| **۶** | **مرخصی و مأموریت** | `HrmLeaveRequest`, `HrmLeaveBalance`, `HrmLeaveType` | تقویم جلالی، تداخل‌یابی، گردش کار تایید و محاسبه مانده مرخصی |
| **۷** | **حقوق و دستمزد (قلب ماژول)** | `IranianPayrollCalculator`, `HrmPayrollRun`, `HrmEmployeeSalary`, `HrmLoan` | قوانین کار ۱۴۰۵، حق مسکن، بن، اولاد، مالیات پله‌ای، دیسکت بیمه تامین اجتماعی و فایل بانک |
| **۸** | **عملکرد و ارزیابی (OKR / 360)** | `HrmPerformanceReview`, `HrmReview360`, `HrmObjective`, `HrmKeyResult` | ارزیابی شایستگی، بازخورد چندمنبعی و اتصال به پاداش |
| **۹** | **آموزش و توسعه (LMS)** | `HrmTrainingCourse`, `HrmTrainingLesson`, `HrmTrainingCertificate` | نیازسنجی، پیگیری جلسات، صدور گواهینامه و مهارت‌ها |
| **۱۰**| **پورتال خودخدمتی پرسنل (ESS)** | `MyPortalPage`, `MyPayrollPage`, `MyRequestsPage`, `MyTimePage`, `MyDocsPage` | دریافت فیش، ثبت مرخصی/مساعده، درخواست گواهی اشتغال |
| **۱۱**| **اموال و دارایی‌های در اختیار** | اتصال به ماژول `Assets` (`AstAsset`) | تحویل لپ‌تاپ، تبلت و ثبت تاریخ بازپس‌گیری |
| **۱۲**| **انضباطی و تخلفات** | `HrmNotice` | ثبت تذکرات، جلسات کمیته انضباطی و احکام مربوطه |
| **۱۳**| **داشبورد و گزارش‌های HR** | `HrmAnalyticsService`, `workforce_budget_page` | هرم جمعیتی، نرخ ترک کار، بودجه پرسنلی و اضافه‌کاری |
| **۱۴**| **اتصال به سایر ماژول‌ها** | `HrmPayrollAccountingBridge` | صدور خودکار سند دوبل حقوق و دستمزد در ماژول `Accounting` |
| **۱۵**| **امنیت و دسترسی سطحی (FLS)** | `HrmEmployeePolicy`, `AuthzFieldPolicy` | عدم نمایش ارقام حقوق و اطلاعات پزشکی به پرسنل عادی |
| **۱۶**| **رفع نواقص سنتی** | پشتیبانی تقویم جلالی، فایل‌های DBF بیمه و TXT شتاب | تولید فایل استاندارد لیست حقوق و بیمه برخط |
