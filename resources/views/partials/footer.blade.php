<!-- Footer -->
<footer class="bg-gray-900 text-white">
    <div class="container mx-auto px-4 py-12">
        <div class="grid grid-cols-1 md:grid-cols-4 gap-8">
            <div>
                <h3 class="text-xl font-bold flex items-center gap-2 mb-4 text-green-400">
                    <i data-lucide="leaf"></i> GreenBite
                </h3>
                <p class="text-gray-400 text-sm">{{ i18n('footer.tagline') }}</p>
            </div>
            <div>
                <h4 class="font-bold mb-4">{{ i18n('footer.quickLinks') }}</h4>
                <ul class="space-y-2 text-sm text-gray-400">
                    <li><a href="{{ url('/catalog') }}" class="hover:text-white transition">{{ i18n('nav.catalog') }}</a></li>
                    <li><a href="{{ url('/subscriptions') }}" class="hover:text-white transition">{{ i18n('nav.subscriptions') }}</a></li>
                    <li><a href="#" class="hover:text-white transition">{{ i18n('footer.aboutUs') }}</a></li>
                </ul>
            </div>
            <div>
                <h4 class="font-bold mb-4">{{ i18n('footer.help') }}</h4>
                <ul class="space-y-2 text-sm text-gray-400">
                    <li><a href="#" class="hover:text-white transition">{{ i18n('footer.faq') }}</a></li>
                    <li><a href="#" class="hover:text-white transition">{{ i18n('footer.shipping') }}</a></li>
                    <li><a href="#" class="hover:text-white transition">{{ i18n('footer.privacy') }}</a></li>
                    <li><a href="#" class="hover:text-white transition">{{ i18n('footer.terms') }}</a></li>
                    <li><a href="#" class="hover:text-white transition">{{ i18n('footer.contact') }}</a></li>
                </ul>
            </div>
            <div>
                <h4 class="font-bold mb-4">{{ i18n('footer.newsletter') }}</h4>
                <div class="flex">
                    <input type="email" placeholder="{{ i18n('footer.newsletterPlaceholder') }}" class="px-3 py-2 rounded-l-lg w-full text-black focus:outline-none">
                    <button class="bg-green-600 px-4 py-2 rounded-r-lg hover:bg-green-500 transition">{{ i18n('common.subscribe') }}</button>
                </div>
            </div>
        </div>
        <div class="border-t border-gray-800 mt-8 pt-8 text-center text-sm text-gray-500">
            {{ i18n('footer.copyright') }}
        </div>
    </div>
</footer>
