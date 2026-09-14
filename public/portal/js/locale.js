'use strict';

const Locale = {
  current: 'en',
  init() {
    const saved = localStorage.getItem(FPS.localeKey);
    this.current = ['en', 'hi'].includes(saved) ? saved : 'en';
    document.documentElement.lang = this.current;
  },
  set(code) {
    this.current = ['en', 'hi'].includes(code) ? code : 'en';
    localStorage.setItem(FPS.localeKey, this.current);
    document.documentElement.lang = this.current;
    this.notify();
  },
  t(key) {
    const table = FPS.labels && FPS.labels[this.current];
    return (table && table[key]) || (FPS.labels && FPS.labels.en[key]) || key;
  },
  subscribers: [],
  onChanged(fn) { this.subscribers.push(fn); },
  notify() { this.subscribers.forEach((fn) => fn(this.current)); },
};