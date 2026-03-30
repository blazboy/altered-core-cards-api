import { Controller } from '@hotwired/stimulus';

export default class extends Controller {
    static targets = ['input', 'dropdown', 'list'];
    static values  = { url: String };

    #abortController = null;
    #selected        = false;

    connect() {
        document.addEventListener('click', this.#onOutsideClick);
    }

    disconnect() {
        document.removeEventListener('click', this.#onOutsideClick);
    }

    async search() {
        const q = this.inputTarget.value.trim();
        this.#selected = false;

        if (q.length < 2) {
            this.#hide();
            return;
        }

        this.#abortController?.abort();
        this.#abortController = new AbortController();

        try {
            const res  = await fetch(`${this.urlValue}?q=${encodeURIComponent(q)}`, {
                signal: this.#abortController.signal,
            });
            const data = await res.json();
            this.#render(data);
        } catch {
            // aborted or network error
        }
    }

    select(event) {
        this.inputTarget.value = event.currentTarget.dataset.value;
        this.#selected = true;
        this.#hide();
        // submit on selection
        this.inputTarget.closest('form').submit();
    }

    #render(items) {
        this.listTarget.innerHTML = '';

        if (!items.length) {
            this.#hide();
            return;
        }

        items.forEach(item => {
            const li = document.createElement('li');
            li.dataset.value = item;
            li.textContent   = item;
            li.className     = 'px-3 py-2 text-sm text-gray-700 dark:text-gray-300 cursor-pointer hover:bg-blue-50 dark:hover:bg-gray-600 truncate';
            li.addEventListener('mousedown', this.select.bind(this));
            this.listTarget.appendChild(li);
        });

        this.dropdownTarget.classList.remove('hidden');
    }

    #hide() {
        this.dropdownTarget.classList.add('hidden');
    }

    #onOutsideClick = (e) => {
        if (!this.element.contains(e.target)) {
            this.#hide();
        }
    };
}
