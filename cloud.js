window.cloudDrive = {
    uid: localStorage.getItem('uid'),
    apiUrl: 'http://123.60.174.101:48053/cloud/api.php',

    async fetch(action, data = {}, method = 'POST') {
        const form = new FormData();
        form.append('uid', this.uid);
        for (let k in data) form.append(k, data[k]);
        const res = await fetch(`${this.apiUrl}?action=${action}`, { method, body: form });
        return res.json();
    },

    async getFiles() {
        return this.fetch('list', {}, 'POST');
    },

    async uploadFiles(fileList) {
        const form = new FormData();
        form.append('uid', this.uid);
        for (let file of fileList) form.append('files[]', file);
        return fetch(`${this.apiUrl}?action=upload`, { method: 'POST', body: form })
            .then(res => res.json());
    },

    async deleteFile(name) {
        return this.fetch('delete', { name });
    },

    downloadFile(name) {
        window.open(`${this.apiUrl}?action=download&name=${encodeURIComponent(name)}&uid=${this.uid}`);
    },

    formatFileSize(bytes) {
        if (bytes < 1024) return bytes + ' B';
        else if (bytes < 1048576) return (bytes / 1024).toFixed(1) + ' KB';
        else return (bytes / 1048576).toFixed(1) + ' MB';
    }
};
