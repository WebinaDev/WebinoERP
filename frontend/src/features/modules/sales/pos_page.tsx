'use client';

import React, { useState, useEffect } from 'react';
import { useTranslations } from 'next-intl';
import { Card, CardContent, CardHeader, CardTitle, CardDescription } from '@/components/ui/card';
import { Button } from '@/components/ui/button';
import { Badge } from '@/components/ui/badge';
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/components/ui/table';
import { Tabs, TabsContent, TabsList, TabsTrigger } from '@/components/ui/tabs';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import {
  Utensils,
  CreditCard,
  ChefHat,
  Users,
  Clock,
  CheckCircle2,
  AlertTriangle,
  Flame,
  Coffee,
  Plus,
  Minus,
  Trash2,
  DollarSign,
  Printer,
  Smartphone,
  ShieldAlert,
  Search,
  Maximize2,
  Volume2,
  Layers,
  ArrowRight,
  TrendingUp,
  Receipt,
  Store,
} from 'lucide-react';

interface MenuItem {
  id: number;
  name: string;
  category: 'hot_drinks' | 'cold_drinks' | 'food' | 'dessert';
  price: number;
  stock: number;
  iconName: string;
}

interface CartItem extends MenuItem {
  quantity: number;
  notes?: string;
}

interface RestaurantTable {
  id: number;
  number: string;
  zone: 'salon' | 'outdoor' | 'vip';
  status: 'free' | 'occupied' | 'reserved' | 'preparing';
  seats: number;
  currentTotal?: number;
}

interface KitchenTicket {
  id: number;
  orderNumber: string;
  table: string;
  type: 'dine_in' | 'takeaway' | 'delivery';
  elapsedMinutes: number;
  items: Array<{ name: string; quantity: number; notes?: string }>;
  status: 'pending' | 'preparing' | 'ready';
  station: 'kitchen' | 'bar';
}

const MENU_ITEMS: MenuItem[] = [
  { id: 1, name: 'اسپرسو دبل ریسترتو', category: 'hot_drinks', price: 650000, stock: 120, iconName: 'Coffee' },
  { id: 2, name: 'کاپوچینو وانیل', category: 'hot_drinks', price: 950000, stock: 85, iconName: 'Coffee' },
  { id: 3, name: 'آیس لاته کارامل', category: 'cold_drinks', price: 1100000, stock: 40, iconName: 'Flame' },
  { id: 4, name: 'برگر کلاسیک زغالی', category: 'food', price: 2850000, stock: 25, iconName: 'Utensils' },
  { id: 5, name: 'پیتزا چانو بیف', category: 'food', price: 3900000, stock: 18, iconName: 'Utensils' },
  { id: 6, name: 'چیزکیک سن سباستین', category: 'dessert', price: 1450000, stock: 14, iconName: 'Flame' },
];

const INITIAL_TABLES: RestaurantTable[] = [
  { id: 1, number: 'میز ۱ (سالن)', zone: 'salon', status: 'occupied', seats: 4, currentTotal: 3450000 },
  { id: 2, number: 'میز ۲ (سالن)', zone: 'salon', status: 'free', seats: 2 },
  { id: 3, number: 'میز ۳ (تراس)', zone: 'outdoor', status: 'preparing', seats: 6, currentTotal: 7800000 },
  { id: 4, number: 'میز ۴ (VIP)', zone: 'vip', status: 'reserved', seats: 8 },
  { id: 5, number: 'میز ۵ (سالن)', zone: 'salon', status: 'free', seats: 4 },
  { id: 6, number: 'میز ۶ (تراس)', zone: 'outdoor', status: 'free', seats: 2 },
];

const INITIAL_TICKETS: KitchenTicket[] = [
  {
    id: 101,
    orderNumber: '#ORD-402',
    table: 'میز ۳ (تراس)',
    type: 'dine_in',
    elapsedMinutes: 14,
    items: [
      { name: 'پیتزا چانو بیف', quantity: 2, notes: 'بدون پیاز' },
      { name: 'آیس لاته کارامل', quantity: 2 },
    ],
    status: 'preparing',
    station: 'kitchen',
  },
  {
    id: 102,
    orderNumber: '#ORD-403',
    table: 'بیرون‌بر پیک #۱۲',
    type: 'delivery',
    elapsedMinutes: 4,
    items: [
      { name: 'برگر کلاسیک زغالی', quantity: 1, notes: 'سس تند اضافه' },
      { name: 'چیزکیک سن سباستین', quantity: 1 },
    ],
    status: 'pending',
    station: 'kitchen',
  },
  {
    id: 103,
    orderNumber: '#ORD-404',
    table: 'میز ۱ (سالن)',
    type: 'dine_in',
    elapsedMinutes: 2,
    items: [
      { name: 'اسپرسو دبل ریسترتو', quantity: 2 },
      { name: 'کاپوچینو وانیل', quantity: 1 },
    ],
    status: 'pending',
    station: 'bar',
  },
];

export function PosOverviewPage() {
  const t = useTranslations('erp.pos');
  const [activeTab, setActiveTab] = useState<'cashier' | 'kds' | 'manager' | 'waiter' | 'shift'>('cashier');

  // Cashier State
  const [cart, setCart] = useState<CartItem[]>([]);
  const [selectedCategory, setSelectedCategory] = useState<string>('all');
  const [searchQuery, setSearchQuery] = useState('');
  const [selectedTable, setSelectedTable] = useState('میز ۲ (سالن)');
  const [orderType, setOrderType] = useState<'dine_in' | 'takeaway' | 'delivery'>('dine_in');
  const [paymentModalOpen, setPaymentModalOpen] = useState(false);
  const [paidSuccess, setPaidSuccess] = useState(false);

  // KDS State
  const [tickets, setTickets] = useState<KitchenTicket[]>(INITIAL_TICKETS);
  const [kdsStation, setKdsStation] = useState<'all' | 'kitchen' | 'bar'>('all');

  const addToCart = (item: MenuItem) => {
    setCart((prev) => {
      const existing = prev.find((i) => i.id === item.id);
      if (existing) {
        return prev.map((i) => (i.id === item.id ? { ...i, quantity: i.quantity + 1 } : i));
      }
      return [...prev, { ...item, quantity: 1 }];
    });
  };

  const updateQuantity = (id: number, delta: number) => {
    setCart((prev) =>
      prev
        .map((i) => (i.id === id ? { ...i, quantity: i.quantity + delta } : i))
        .filter((i) => i.quantity > 0)
    );
  };

  const clearCart = () => setCart([]);

  const subtotal = cart.reduce((acc, item) => acc + item.price * item.quantity, 0);
  const tax = subtotal * 0.1;
  const total = subtotal + tax;

  const handleCheckout = () => {
    setPaymentModalOpen(true);
  };

  const executePayment = () => {
    setPaidSuccess(true);
    setTimeout(() => {
      setPaidSuccess(false);
      setPaymentModalOpen(false);
      clearCart();
    }, 1500);
  };

  const advanceTicketStatus = (ticketId: number) => {
    setTickets((prev) =>
      prev.map((t) => {
        if (t.id === ticketId) {
          const nextStatus = t.status === 'pending' ? 'preparing' : 'ready';
          return { ...t, status: nextStatus };
        }
        return t;
      })
    );
  };

  return (
    <div className="space-y-6" dir="rtl">
      {/* Top Header bar with Shift & Cash Drawer status */}
      <div className="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between border-b pb-4">
        <div>
          <h1 className="text-2xl font-bold tracking-tight flex items-center gap-2">
            <Store className="h-6 w-6 text-primary" />
            {t('title')}
          </h1>
          <p className="text-sm text-muted-foreground mt-0.5">
            {t('description')}
          </p>
        </div>

        <div className="flex items-center gap-2">
          <Badge variant="outline" className="bg-emerald-50 text-emerald-700 border-emerald-200 text-xs px-2.5 py-1 flex items-center gap-1.5">
            <span className="h-2 w-2 rounded-full bg-emerald-500 animate-pulse" />
            شیفت فعال: صندوق ۱ (رضا محمدی)
          </Badge>
          <Badge variant="outline" className="text-xs font-mono">
            کشوی نقد: بسته ✓ (موجودی: ۵,۰۰۰,۰۰۰ ریال)
          </Badge>
          <Button size="sm" variant="outline" onClick={() => setActiveTab('shift')}>
            <Receipt className="h-4 w-4 me-1.5" />
            بستن شیفت
          </Button>
        </div>
      </div>

      {/* Main Tabs */}
      <Tabs value={activeTab} onValueChange={(v) => setActiveTab(v as any)} className="w-full">
        <TabsList className="grid w-full grid-cols-5 max-w-3xl">
          <TabsTrigger value="cashier" className="gap-1.5">
            <Store className="h-4 w-4" />
            صندوقدار (POS)
          </TabsTrigger>
          <TabsTrigger value="kds" className="gap-1.5">
            <ChefHat className="h-4 w-4" />
            نمایشگر آشپزخانه (KDS)
          </TabsTrigger>
          <TabsTrigger value="manager" className="gap-1.5">
            <TrendingUp className="h-4 w-4" />
            داشبورد مدیریت
          </TabsTrigger>
          <TabsTrigger value="waiter" className="gap-1.5">
            <Smartphone className="h-4 w-4" />
            سفارش‌گیر سالن (Waiter)
          </TabsTrigger>
          <TabsTrigger value="shift" className="gap-1.5">
            <Receipt className="h-4 w-4" />
            تطبیق شیفت و کشو
          </TabsTrigger>
        </TabsList>

        {/* ── 1. Cashier POS Tab (Touch-First Sub-10s Ordering) ── */}
        <TabsContent value="cashier" className="mt-4 space-y-4">
          <div className="grid gap-4 lg:grid-cols-12">
            {/* Left 8 Cols: Quick Menu & Table Bar */}
            <div className="lg:col-span-8 space-y-4">
              {/* Table / Order-type selector bar */}
              <div className="flex items-center justify-between p-3 rounded-lg border bg-card">
                <div className="flex items-center gap-2">
                  <span className="text-xs font-semibold text-muted-foreground">نوع سفارش:</span>
                  <div className="flex gap-1">
                    <Button
                      size="sm"
                      variant={orderType === 'dine_in' ? 'default' : 'outline'}
                      className="text-xs h-8"
                      onClick={() => setOrderType('dine_in')}
                    >
                      سالن (میز)
                    </Button>
                    <Button
                      size="sm"
                      variant={orderType === 'takeaway' ? 'default' : 'outline'}
                      className="text-xs h-8"
                      onClick={() => setOrderType('takeaway')}
                    >
                      بیرون‌بر
                    </Button>
                    <Button
                      size="sm"
                      variant={orderType === 'delivery' ? 'default' : 'outline'}
                      className="text-xs h-8"
                      onClick={() => setOrderType('delivery')}
                    >
                      پیک موتوری
                    </Button>
                  </div>
                </div>

                <div className="flex items-center gap-2">
                  <span className="text-xs text-muted-foreground">میز انتخاب‌شده:</span>
                  <Badge variant="secondary" className="font-bold text-xs">
                    {selectedTable}
                  </Badge>
                </div>
              </div>

              {/* Categories & Search */}
              <div className="flex items-center gap-2">
                <div className="relative flex-1">
                  <Search className="absolute right-3 top-2.5 h-4 w-4 text-muted-foreground" />
                  <Input
                    placeholder="جستجوی سریع محصول یا اسکن بارکد..."
                    className="pr-9 text-xs h-9"
                    value={searchQuery}
                    onChange={(e) => setSearchQuery(e.target.value)}
                  />
                </div>
                <div className="flex gap-1 overflow-x-auto">
                  <Button
                    size="sm"
                    variant={selectedCategory === 'all' ? 'default' : 'outline'}
                    className="text-xs h-9"
                    onClick={() => setSelectedCategory('all')}
                  >
                    همه
                  </Button>
                  <Button
                    size="sm"
                    variant={selectedCategory === 'hot_drinks' ? 'default' : 'outline'}
                    className="text-xs h-9"
                    onClick={() => setSelectedCategory('hot_drinks')}
                  >
                    نوشیدنی گرم
                  </Button>
                  <Button
                    size="sm"
                    variant={selectedCategory === 'cold_drinks' ? 'default' : 'outline'}
                    className="text-xs h-9"
                    onClick={() => setSelectedCategory('cold_drinks')}
                  >
                    نوشیدنی سرد
                  </Button>
                  <Button
                    size="sm"
                    variant={selectedCategory === 'food' ? 'default' : 'outline'}
                    className="text-xs h-9"
                    onClick={() => setSelectedCategory('food')}
                  >
                    غذا
                  </Button>
                  <Button
                    size="sm"
                    variant={selectedCategory === 'dessert' ? 'default' : 'outline'}
                    className="text-xs h-9"
                    onClick={() => setSelectedCategory('dessert')}
                  >
                    دسر
                  </Button>
                </div>
              </div>

              {/* Touch Menu Grid (Big tappable buttons) */}
              <div className="grid grid-cols-2 sm:grid-cols-3 gap-3">
                {MENU_ITEMS.filter(
                  (item) =>
                    (selectedCategory === 'all' || item.category === selectedCategory) &&
                    (searchQuery === '' || item.name.includes(searchQuery))
                ).map((item) => (
                  <button
                    key={item.id}
                    onClick={() => addToCart(item)}
                    className="p-4 rounded-xl border bg-card hover:bg-muted/40 transition-all text-start flex flex-col justify-between h-28 shadow-sm active:scale-95 group focus:outline-none focus:ring-2 focus:ring-primary"
                  >
                    <div className="flex justify-between items-start w-full">
                      <span className="font-bold text-sm text-foreground group-hover:text-primary transition-colors">
                        {item.name}
                      </span>
                      <Badge variant="secondary" className="text-[10px] px-1.5 py-0">
                        موجودی: {item.stock}
                      </Badge>
                    </div>
                    <div className="flex justify-between items-end w-full mt-2">
                      <span className="font-mono text-xs font-bold text-emerald-600">
                        {item.price.toLocaleString('fa-IR')} ریال
                      </span>
                      <span className="p-1 rounded-full bg-primary/10 text-primary">
                        <Plus className="h-4 w-4" />
                      </span>
                    </div>
                  </button>
                ))}
              </div>
            </div>

            {/* Right 4 Cols: Live Cart & Instant POS Checkout */}
            <div className="lg:col-span-4">
              <Card className="shadow-md flex flex-col h-full border-primary/20">
                <CardHeader className="p-4 pb-2 border-b">
                  <div className="flex justify-between items-center">
                    <CardTitle className="text-base flex items-center gap-1.5">
                      <Receipt className="h-4 w-4 text-primary" />
                      سبد سفارش جاری
                    </CardTitle>
                    {cart.length > 0 && (
                      <Button size="sm" variant="ghost" className="h-7 text-xs text-rose-600" onClick={clearCart}>
                        <Trash2 className="h-3.5 w-3.5 me-1" />
                        خالی‌کردن
                      </Button>
                    )}
                  </div>
                </CardHeader>
                <CardContent className="p-4 flex-1 flex flex-col justify-between space-y-4">
                  {/* Cart Line Items */}
                  <div className="space-y-2 overflow-y-auto max-h-[340px]">
                    {cart.length === 0 ? (
                      <div className="py-12 text-center text-muted-foreground text-xs">
                        سبد خالی است. آیتمی را از منوی روبرو لمس کنید.
                      </div>
                    ) : (
                      cart.map((line) => (
                        <div key={line.id} className="p-2.5 rounded-lg border bg-muted/20 flex justify-between items-center text-xs">
                          <div className="space-y-0.5">
                            <div className="font-bold">{line.name}</div>
                            <div className="text-[11px] font-mono text-muted-foreground">
                              {(line.price * line.quantity).toLocaleString('fa-IR')} ریال
                            </div>
                          </div>
                          <div className="flex items-center gap-1.5 bg-background border rounded-lg p-0.5">
                            <button
                              onClick={() => updateQuantity(line.id, -1)}
                              className="p-1 hover:bg-muted rounded text-rose-600"
                            >
                              <Minus className="h-3 w-3" />
                            </button>
                            <span className="w-5 text-center font-mono font-bold text-xs">
                              {line.quantity}
                            </span>
                            <button
                              onClick={() => updateQuantity(line.id, 1)}
                              className="p-1 hover:bg-muted rounded text-primary"
                            >
                              <Plus className="h-3 w-3" />
                            </button>
                          </div>
                        </div>
                      ))
                    )}
                  </div>

                  {/* Totals & Checkout Button */}
                  <div className="space-y-3 pt-3 border-t">
                    <div className="flex justify-between text-xs text-muted-foreground">
                      <span>جمع اقلام:</span>
                      <span className="font-mono">{subtotal.toLocaleString('fa-IR')} ریال</span>
                    </div>
                    <div className="flex justify-between text-xs text-muted-foreground">
                      <span>مالیات و عوارض (۱۰٪):</span>
                      <span className="font-mono">{tax.toLocaleString('fa-IR')} ریال</span>
                    </div>
                    <div className="flex justify-between text-sm font-bold border-t pt-2">
                      <span>مبلغ قابل پرداخت:</span>
                      <span className="font-mono text-emerald-600">{total.toLocaleString('fa-IR')} ریال</span>
                    </div>

                    <div className="grid grid-cols-2 gap-2 pt-2">
                      <Button
                        variant="outline"
                        size="sm"
                        disabled={cart.length === 0}
                        className="text-xs"
                      >
                        <Printer className="h-3.5 w-3.5 me-1" />
                        پیش‌فاکتور
                      </Button>
                      <Button
                        size="sm"
                        disabled={cart.length === 0}
                        className="text-xs bg-emerald-600 hover:bg-emerald-700"
                        onClick={handleCheckout}
                      >
                        <CreditCard className="h-3.5 w-3.5 me-1" />
                        پرداخت سریع (F12)
                      </Button>
                    </div>
                  </div>
                </CardContent>
              </Card>
            </div>
          </div>

          {/* Payment Modal */}
          {paymentModalOpen && (
            <div className="fixed inset-0 z-50 bg-black/60 flex items-center justify-center p-4">
              <Card className="w-full max-w-md shadow-2xl animate-in zoom-in-95">
                <CardHeader className="pb-3 border-b">
                  <CardTitle className="text-base flex items-center justify-between">
                    <span>تسویه و دریافت فیش</span>
                    <Badge variant="outline" className="text-xs font-mono">
                      {total.toLocaleString('fa-IR')} ریال
                    </Badge>
                  </CardTitle>
                </CardHeader>
                <CardContent className="space-y-4 p-4">
                  {paidSuccess ? (
                    <div className="p-6 text-center space-y-2">
                      <CheckCircle2 className="h-12 w-12 text-emerald-600 mx-auto" />
                      <div className="text-base font-bold text-emerald-700">تراکنش با موفقیت ثبت شد!</div>
                      <p className="text-xs text-muted-foreground">دستور چاپ فیش حرارتی به پرینتر ارسال شد.</p>
                    </div>
                  ) : (
                    <>
                      <div className="grid grid-cols-2 gap-3">
                        <Button
                          variant="outline"
                          className="h-20 flex flex-col items-center justify-center gap-1.5 border-emerald-300 bg-emerald-50/50 hover:bg-emerald-100/50 text-emerald-800"
                          onClick={executePayment}
                        >
                          <CreditCard className="h-6 w-6 text-emerald-600" />
                          <span className="text-xs font-bold">کارتخوان PC-POS سامان‌کیش</span>
                        </Button>
                        <Button
                          variant="outline"
                          className="h-20 flex flex-col items-center justify-center gap-1.5 border-blue-300 bg-blue-50/50 hover:bg-blue-100/50 text-blue-800"
                          onClick={executePayment}
                        >
                          <DollarSign className="h-6 w-6 text-blue-600" />
                          <span className="text-xs font-bold">دریافت نقدی (Cash Drawer)</span>
                        </Button>
                      </div>

                      <div className="p-3 bg-muted/30 rounded text-xs space-y-1">
                        <div className="flex justify-between">
                          <span className="text-muted-foreground">کارتخوان متصل:</span>
                          <span className="font-mono">192.168.1.180 (Online)</span>
                        </div>
                        <div className="flex justify-between">
                          <span className="text-muted-foreground">چاپگر فیش:</span>
                          <span>Bixolon SRP-350 (80mm)</span>
                        </div>
                      </div>

                      <div className="flex justify-end gap-2 pt-2">
                        <Button variant="ghost" size="sm" onClick={() => setPaymentModalOpen(false)}>
                          انصراف
                        </Button>
                        <Button size="sm" onClick={executePayment}>
                          تأیید پرداخت و خروج
                        </Button>
                      </div>
                    </>
                  )}
                </CardContent>
              </Card>
            </div>
          )}
        </TabsContent>

        {/* ── 2. Kitchen Display System (KDS) Tab ── */}
        <TabsContent value="kds" className="mt-4 space-y-4">
          <div className="flex justify-between items-center p-3 rounded-lg border bg-card">
            <div className="flex items-center gap-2">
              <ChefHat className="h-5 w-5 text-primary" />
              <span className="font-bold text-sm">صف زنده سفارشات آشپزخانه و بار (KDS Station)</span>
            </div>

            <div className="flex items-center gap-2">
              <Button
                size="sm"
                variant={kdsStation === 'all' ? 'default' : 'outline'}
                className="text-xs h-8"
                onClick={() => setKdsStation('all')}
              >
                همه ایستگاه‌ها
              </Button>
              <Button
                size="sm"
                variant={kdsStation === 'kitchen' ? 'default' : 'outline'}
                className="text-xs h-8"
                onClick={() => setKdsStation('kitchen')}
              >
                آشپزخانه گرم
              </Button>
              <Button
                size="sm"
                variant={kdsStation === 'bar' ? 'default' : 'outline'}
                className="text-xs h-8"
                onClick={() => setKdsStation('bar')}
              >
                بار و نوشیدنی
              </Button>
            </div>
          </div>

          {/* Ticket Grid (Touch Cards with lifecycle timers) */}
          <div className="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
            {tickets
              .filter((t) => kdsStation === 'all' || t.station === kdsStation)
              .map((ticket) => {
                const isLate = ticket.elapsedMinutes > 10;
                return (
                  <Card
                    key={ticket.id}
                    className={`border-2 shadow-sm ${
                      ticket.status === 'ready'
                        ? 'border-emerald-500 bg-emerald-50/30'
                        : isLate
                        ? 'border-rose-500 bg-rose-50/20'
                        : 'border-amber-400 bg-amber-50/20'
                    }`}
                  >
                    <CardHeader className="p-3 pb-2 border-b flex flex-row items-center justify-between">
                      <div>
                        <span className="font-bold text-sm">{ticket.orderNumber}</span>
                        <span className="text-xs text-muted-foreground me-2">({ticket.table})</span>
                      </div>
                      <Badge
                        variant="outline"
                        className={
                          isLate
                            ? 'bg-rose-100 text-rose-800 border-rose-300 text-xs'
                            : 'bg-amber-100 text-amber-800 border-amber-300 text-xs'
                        }
                      >
                        <Clock className="h-3 w-3 me-1" />
                        {ticket.elapsedMinutes} دقیقه
                      </Badge>
                    </CardHeader>
                    <CardContent className="p-3 space-y-3">
                      <div className="space-y-1.5">
                        {ticket.items.map((item, idx) => (
                          <div key={idx} className="flex justify-between items-center text-xs">
                            <span className="font-bold">
                              {item.quantity} × {item.name}
                            </span>
                            {item.notes && (
                              <Badge variant="secondary" className="text-[10px] text-rose-600 bg-rose-50">
                                {item.notes}
                              </Badge>
                            )}
                          </div>
                        ))}
                      </div>

                      <div className="pt-2 border-t flex justify-end">
                        <Button
                          size="sm"
                          className={`w-full text-xs font-bold ${
                            ticket.status === 'pending'
                              ? 'bg-amber-600 hover:bg-amber-700'
                              : ticket.status === 'preparing'
                              ? 'bg-emerald-600 hover:bg-emerald-700'
                              : 'bg-muted text-muted-foreground'
                          }`}
                          onClick={() => advanceTicketStatus(ticket.id)}
                        >
                          {ticket.status === 'pending'
                            ? 'شروع آماده‌سازی'
                            : ticket.status === 'preparing'
                            ? 'علامت‌گذاری به عنوان آماده سرو ✓'
                            : 'تحویل داده شد'}
                        </Button>
                      </div>
                    </CardContent>
                  </Card>
                );
              })}
          </div>
        </TabsContent>

        {/* ── 3. Manager & Operational Cockpit Tab ── */}
        <TabsContent value="manager" className="mt-4 space-y-4">
          <div className="grid gap-4 md:grid-cols-4">
            <Card className="shadow-sm">
              <CardHeader className="p-4 pb-2">
                <CardTitle className="text-xs font-medium text-muted-foreground flex items-center justify-between">
                  فروش امروز شعبه
                  <DollarSign className="h-4 w-4 text-emerald-600" />
                </CardTitle>
              </CardHeader>
              <CardContent className="p-4 pt-1">
                <div className="text-xl font-bold">۲۴,۵۰۰,۰۰۰ ریال</div>
                <p className="text-xs text-muted-foreground mt-0.5">۳۲ سفارش تکمیل‌شده (+۱۸٪ نسبت به دیروز)</p>
              </CardContent>
            </Card>

            <Card className="shadow-sm">
              <CardHeader className="p-4 pb-2">
                <CardTitle className="text-xs font-medium text-muted-foreground flex items-center justify-between">
                  نرخ اشغال میزها
                  <Users className="h-4 w-4 text-blue-600" />
                </CardTitle>
              </CardHeader>
              <CardContent className="p-4 pt-1">
                <div className="text-xl font-bold text-blue-600">۶۸٪ اشغال</div>
                <p className="text-xs text-muted-foreground mt-0.5">۴ میز اشغال / ۲ میز آزاد</p>
              </CardContent>
            </Card>

            <Card className="shadow-sm">
              <CardHeader className="p-4 pb-2">
                <CardTitle className="text-xs font-medium text-muted-foreground flex items-center justify-between">
                  میانگین زمان آماده‌سازی (KDS)
                  <Clock className="h-4 w-4 text-amber-500" />
                </CardTitle>
              </CardHeader>
              <CardContent className="p-4 pt-1">
                <div className="text-xl font-bold text-amber-600">۸.۵ دقیقه</div>
                <p className="text-xs text-muted-foreground mt-0.5">هدف استاندارد: زیر ۱۲ دقیقه</p>
              </CardContent>
            </Card>

            <Card className="shadow-sm">
              <CardHeader className="p-4 pb-2">
                <CardTitle className="text-xs font-medium text-muted-foreground flex items-center justify-between">
                  هشدار موجودی بحرانی
                  <AlertTriangle className="h-4 w-4 text-rose-500" />
                </CardTitle>
              </CardHeader>
              <CardContent className="p-4 pt-1">
                <div className="text-xl font-bold text-rose-600">۲ قلم کالا</div>
                <p className="text-xs text-muted-foreground mt-0.5">چیزکیک سن سباستین و استیک بیف</p>
              </CardContent>
            </Card>
          </div>

          <Card className="shadow-sm">
            <CardHeader className="pb-3">
              <CardTitle className="text-base">نقشه و وضعیت میزهای سالن و تراس</CardTitle>
              <CardDescription className="text-xs">
                نمای لحظه‌ای میزهای آزاد، اشغال، رزرو شده و مبالغ باز هر میز.
              </CardDescription>
            </CardHeader>
            <CardContent className="p-0 overflow-x-auto">
              <Table>
                <TableHeader>
                  <TableRow className="bg-muted/40 text-xs">
                    <TableHead>شماره میز</TableHead>
                    <TableHead>منطقه (Zone)</TableHead>
                    <TableHead>ظرفیت</TableHead>
                    <TableHead>وضعیت کنونی</TableHead>
                    <TableHead>مبلغ باز سفارش</TableHead>
                    <TableHead className="text-end">عملیات مدیریت</TableHead>
                  </TableRow>
                </TableHeader>
                <TableBody className="text-sm">
                  {INITIAL_TABLES.map((table) => (
                    <TableRow key={table.id} className="hover:bg-muted/20">
                      <TableCell className="font-bold text-xs">{table.number}</TableCell>
                      <TableCell className="text-xs capitalize">{table.zone}</TableCell>
                      <TableCell className="text-xs font-mono">{table.seats} نفر</TableCell>
                      <TableCell>
                        <Badge
                          variant="outline"
                          className={
                            table.status === 'free'
                              ? 'bg-emerald-50 text-emerald-700 border-emerald-200 text-xs'
                              : table.status === 'occupied'
                              ? 'bg-rose-50 text-rose-700 border-rose-200 text-xs'
                              : table.status === 'preparing'
                              ? 'bg-amber-50 text-amber-700 border-amber-200 text-xs'
                              : 'bg-purple-50 text-purple-700 border-purple-200 text-xs'
                          }
                        >
                          {table.status === 'free'
                            ? 'آزاد'
                            : table.status === 'occupied'
                            ? 'اشغال'
                            : table.status === 'preparing'
                            ? 'در حال آماده‌سازی'
                            : 'رزرو شده'}
                        </Badge>
                      </TableCell>
                      <TableCell className="font-mono text-xs">
                        {table.currentTotal ? `${table.currentTotal.toLocaleString('fa-IR')} ریال` : '—'}
                      </TableCell>
                      <TableCell className="text-end">
                        <Button size="sm" variant="ghost" className="h-7 text-xs text-primary">
                          مشاهده و ویرایش
                        </Button>
                      </TableCell>
                    </TableRow>
                  ))}
                </TableBody>
              </Table>
            </CardContent>
          </Card>
        </TabsContent>

        {/* ── 4. Waiter Tablet Ordering Tab ── */}
        <TabsContent value="waiter" className="mt-4 space-y-4">
          <Card className="shadow-sm">
            <CardHeader className="pb-3">
              <CardTitle className="text-base flex items-center justify-between">
                <span>سفارش‌گیری سر میز (Handheld Waiter Terminal)</span>
                <Badge variant="outline" className="text-xs">
                  تبلت گارسون: سالن ۲
                </Badge>
              </CardTitle>
              <CardDescription className="text-xs">
                رابط کاربری سبک و لمسی برای گارسون‌ها جهت ثبت سفارش سریع سر میز، ارسال مستقیم به KDS و فراخوانی صندوق جهت صدور صورتحساب.
              </CardDescription>
            </CardHeader>
            <CardContent className="space-y-4 p-4">
              <div className="grid grid-cols-2 sm:grid-cols-3 gap-3">
                {INITIAL_TABLES.map((tbl) => (
                  <button
                    key={tbl.id}
                    className={`p-4 rounded-xl border text-start transition-all ${
                      tbl.status === 'free'
                        ? 'border-emerald-300 bg-emerald-50/40 hover:bg-emerald-100/40'
                        : tbl.status === 'occupied'
                        ? 'border-rose-300 bg-rose-50/40 hover:bg-rose-100/40'
                        : 'border-muted bg-muted/20'
                    }`}
                  >
                    <div className="flex justify-between items-center">
                      <span className="font-bold text-sm">{tbl.number}</span>
                      <Badge variant="outline" className="text-[10px]">
                        {tbl.seats} نفره
                      </Badge>
                    </div>
                    <div className="mt-2 text-xs text-muted-foreground">
                      {tbl.status === 'free' ? 'آماده پذیرش مهمان' : 'سفارش ثبت‌شده دارد'}
                    </div>
                  </button>
                ))}
              </div>
            </CardContent>
          </Card>
        </TabsContent>

        {/* ── 5. Shift & Cash Drawer Reconciliation Tab ── */}
        <TabsContent value="shift" className="mt-4 space-y-4">
          <Card className="shadow-sm">
            <CardHeader className="pb-3">
              <CardTitle className="text-base flex items-center justify-between">
                <span>تطبیق نقدینگی کشو و بستن شیفت (Cash Drawer Audit)</span>
                <Receipt className="h-5 w-5 text-primary" />
              </CardTitle>
              <CardDescription className="text-xs">
                محاسبه مجموع فروش نقدی، پوز بانکی و مقایسه موجودی فیزیکی کشو با رقم ثبت‌شده در سیستم جهت احراز عدم مغایرت.
              </CardDescription>
            </CardHeader>
            <CardContent className="space-y-4 p-4">
              <div className="grid gap-4 sm:grid-cols-3">
                <div className="p-3 bg-muted/20 border rounded-lg">
                  <div className="text-xs text-muted-foreground">موجودی اولیه صندوق (Opening Float)</div>
                  <div className="text-lg font-bold font-mono mt-1">۵,۰۰۰,۰۰۰ ریال</div>
                </div>
                <div className="p-3 bg-muted/20 border rounded-lg">
                  <div className="text-xs text-muted-foreground">فروش نقدی ثبت‌شده در سیستم</div>
                  <div className="text-lg font-bold font-mono text-blue-600 mt-1">۸,۴۰۰,۰۰۰ ریال</div>
                </div>
                <div className="p-3 bg-muted/20 border rounded-lg">
                  <div className="text-xs text-muted-foreground">فروش پوز بانکی شتابی</div>
                  <div className="text-lg font-bold font-mono text-emerald-600 mt-1">۱۶,۱۰۰,۰۰۰ ریال</div>
                </div>
              </div>

              <div className="p-4 border rounded-lg bg-muted/10 space-y-3">
                <Label className="text-xs font-bold">شمارش فیزیکی نقدینگی کشو در پایان شیفت (ریال)</Label>
                <div className="flex gap-2 max-w-sm">
                  <Input defaultValue="13400000" className="font-mono text-xs" />
                  <Button size="sm" className="text-xs">
                    تطبیق و ثبت نهایی
                  </Button>
                </div>
                <div className="text-xs text-emerald-700 bg-emerald-50 border border-emerald-200 p-2 rounded">
                  تطبیق موفق: موجودی فیزیکی (۱۳,۴۰۰,۰۰۰ ریال) دقیقاً با جمع موجودی اولیه + فروش نقدی سیستم برابر است. مغایرت: صفر ریال ✓
                </div>
              </div>
            </CardContent>
          </Card>
        </TabsContent>
      </Tabs>
    </div>
  );
}

export function PosCashierPage() {
  return <PosOverviewPage />;
}

export function PosKdsPage() {
  return <PosOverviewPage />;
}

export function PosTablesPage() {
  return <PosOverviewPage />;
}

export function PosShiftPage() {
  return <PosOverviewPage />;
}
