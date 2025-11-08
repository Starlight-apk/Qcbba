// forum.js - 修复内容显示问题的论坛JS
class ForumManager {
    constructor() {
        this.baseUrl = 'http://123.60.174.101:48053';
        this.currentPostType = 'text';
        this.currentImageFile = null;
        this.isAdmin = false;
        this.currentPostId = null;
        this.userLikes = new Set();
        this.loadUserLikes();
    }

    // 加载用户点赞数据
    loadUserLikes() {
        const likes = localStorage.getItem('user_likes');
        if (likes) {
            this.userLikes = new Set(JSON.parse(likes));
        }
    }

    // 保存用户点赞数据
    saveUserLikes() {
        localStorage.setItem('user_likes', JSON.stringify([...this.userLikes]));
    }

    // 检查登录状态
    isLoggedIn() {
        const token = localStorage.getItem('token');
        const username = localStorage.getItem('username');
        const uid = localStorage.getItem('uid');
        return !!(token && username && uid);
    }

    // 获取用户信息
    getUserInfo() {
        return {
            username: localStorage.getItem('username'),
            uid: localStorage.getItem('uid'),
            token: localStorage.getItem('token')
        };
    }

    // 检查管理员权限
    async checkAdminPermission() {
        try {
            const uid = localStorage.getItem('uid');
            if (!uid) return false;
            
            const response = await fetch(`${this.baseUrl}/forum/check_admin.php?uid=${uid}`);
            if (response.ok) {
                const data = await response.json();
                this.isAdmin = data.is_admin || false;
                return this.isAdmin;
            }
        } catch (error) {
            console.error('检查管理员权限失败:', error);
        }
        return false;
    }

    // 显示消息
    showMessage(message, type = 'info') {
        const toast = document.getElementById('toast');
        if (toast) {
            toast.textContent = message;
            toast.style.display = 'block';
            setTimeout(() => {
                toast.style.display = 'none';
            }, 3000);
        }
    }

    // 初始化
    async init() {
        this.initAuth();
        this.initEvents();
        await this.checkAdminPermission();
        this.loadPosts();
    }

    // 初始化认证状态
    initAuth() {
        const isLoggedIn = this.isLoggedIn();
        const addPostBtn = document.getElementById('addPostBtn');
        const loginPrompt = document.getElementById('loginPrompt');

        if (isLoggedIn) {
            if (addPostBtn) addPostBtn.style.display = 'grid';
            if (loginPrompt) loginPrompt.style.display = 'none';
        } else {
            if (addPostBtn) addPostBtn.style.display = 'none';
            if (loginPrompt) loginPrompt.style.display = 'block';
        }
    }

    // 初始化事件
    initEvents() {
        // 发帖按钮
        const addPostBtn = document.getElementById('addPostBtn');
        if (addPostBtn) {
            addPostBtn.addEventListener('click', () => this.openPostModal());
        }

        // 模态框关闭
        const closePostModal = () => this.closePostModal();
        const closeCommentModal = () => this.closeCommentModal();
        
        document.getElementById('closePostModal')?.addEventListener('click', closePostModal);
        document.getElementById('cancelPost')?.addEventListener('click', closePostModal);
        
        document.getElementById('closeCommentModal')?.addEventListener('click', closeCommentModal);

        // 遮罩层
        const overlay = document.getElementById('overlay');
        if (overlay) {
            overlay.addEventListener('click', () => {
                this.closePostModal();
                this.closeCommentModal();
            });
        }

        // 帖子类型切换
        document.querySelectorAll('.post-type').forEach(type => {
            type.addEventListener('click', (e) => {
                const postType = e.currentTarget.getAttribute('data-type');
                this.switchPostType(postType);
            });
        });

        // 图片上传
        const imageInput = document.getElementById('imageInput');
        const imageUploadBtn = document.getElementById('imageUploadBtn');
        if (imageInput && imageUploadBtn) {
            imageUploadBtn.addEventListener('click', () => imageInput.click());
            imageInput.addEventListener('change', (e) => this.handleImageUpload(e));
        }

        // 提交帖子
        const submitPost = document.getElementById('submitPost');
        if (submitPost) {
            submitPost.addEventListener('click', () => this.handleSubmitPost());
        }

        // 提交评论
        const submitComment = document.getElementById('submitComment');
        if (submitComment) {
            submitComment.addEventListener('click', () => this.handleSubmitComment());
        }

        // 评论输入框回车提交
        const commentInput = document.getElementById('commentInput');
        if (commentInput) {
            commentInput.addEventListener('keypress', (e) => {
                if (e.key === 'Enter' && e.ctrlKey) {
                    this.handleSubmitComment();
                }
            });
        }
    }

    // 打开发帖模态框
    openPostModal() {
        if (!this.isLoggedIn()) {
            this.showMessage('请先登录', 'error');
            window.location.href = `${this.baseUrl}/login/index.html`;
            return;
        }

        const postModal = document.getElementById('postModal');
        const overlay = document.getElementById('overlay');
        
        if (postModal && overlay) {
            postModal.classList.add('active');
            overlay.classList.add('show');
            this.resetPostForm();
        }
    }

    // 关闭发帖模态框
    closePostModal() {
        const postModal = document.getElementById('postModal');
        const overlay = document.getElementById('overlay');
        
        if (postModal) postModal.classList.remove('active');
        if (overlay) overlay.classList.remove('show');
    }

    // 打开评论模态框
    openCommentModal(postId, postTitle, postAuthor) {
        if (!this.isLoggedIn()) {
            this.showMessage('请先登录', 'error');
            window.location.href = `${this.baseUrl}/login/index.html`;
            return;
        }

        this.currentPostId = postId;
        
        const commentModal = document.getElementById('commentModal');
        const overlay = document.getElementById('overlay');
        const commentPostTitle = document.getElementById('commentPostTitle');
        const commentPostAuthor = document.getElementById('commentPostAuthor');
        
        if (commentModal && overlay && commentPostTitle && commentPostAuthor) {
            commentPostTitle.textContent = this.escapeHtml(postTitle);
            commentPostAuthor.textContent = `作者: ${this.escapeHtml(postAuthor)}`;
            commentModal.classList.add('active');
            overlay.classList.add('show');
            
            // 清空评论输入框
            const commentInput = document.getElementById('commentInput');
            if (commentInput) commentInput.value = '';
            
            this.loadComments(postId);
        }
    }

    // 关闭评论模态框
    closeCommentModal() {
        const commentModal = document.getElementById('commentModal');
        const overlay = document.getElementById('overlay');
        
        if (commentModal) commentModal.classList.remove('active');
        if (overlay) overlay.classList.remove('show');
        
        this.currentPostId = null;
    }

    // 切换帖子类型
    switchPostType(type) {
        document.querySelectorAll('.post-type').forEach(t => t.classList.remove('active'));
        document.querySelector(`[data-type="${type}"]`).classList.add('active');
        
        const imageUploadSection = document.getElementById('imageUploadSection');
        if (imageUploadSection) {
            imageUploadSection.style.display = type === 'image' ? 'block' : 'none';
        }
        
        this.currentPostType = type;
    }

    // 处理图片上传
    handleImageUpload(event) {
        const file = event.target.files[0];
        const imagePreview = document.getElementById('imagePreview');
        const fileName = document.getElementById('fileName');
        
        if (file) {
            if (fileName) fileName.textContent = file.name;
            
            if (imagePreview) {
                const reader = new FileReader();
                reader.onload = function(e) {
                    imagePreview.src = e.target.result;
                    imagePreview.style.display = 'block';
                };
                reader.readAsDataURL(file);
            }
            
            this.currentImageFile = file;
        }
    }

    // 处理提交帖子
    async handleSubmitPost() {
        const title = document.getElementById('postTitle').value.trim();
        const content = document.getElementById('postContent').value.trim();
        const submitBtn = document.getElementById('submitPost');

        // 验证输入
        if (!title) {
            this.showMessage('请输入帖子标题', 'error');
            return;
        }
        if (!content) {
            this.showMessage('请输入帖子内容', 'error');
            return;
        }
        if (this.currentPostType === 'image' && !this.currentImageFile) {
            this.showMessage('请选择图片', 'error');
            return;
        }

        // 设置加载状态
        submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> 发布中...';
        submitBtn.disabled = true;

        try {
            await this.publishPost(title, content, this.currentPostType, this.currentImageFile);
            this.showMessage('帖子发布成功！');
            this.closePostModal();
            this.loadPosts();
        } catch (error) {
            this.showMessage('发布失败: ' + error.message, 'error');
        } finally {
            submitBtn.innerHTML = '发布';
            submitBtn.disabled = false;
        }
    }

    // 处理提交评论
    async handleSubmitComment() {
        const commentInput = document.getElementById('commentInput');
        const submitBtn = document.getElementById('submitComment');
        const content = commentInput.value.trim();

        if (!content) {
            this.showMessage('请输入评论内容', 'error');
            return;
        }

        if (!this.currentPostId) {
            this.showMessage('帖子ID不存在', 'error');
            return;
        }

        // 设置加载状态
        submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> 发布中...';
        submitBtn.disabled = true;

        try {
            await this.publishComment(this.currentPostId, content);
            this.showMessage('评论发布成功！');
            commentInput.value = '';
            this.loadComments(this.currentPostId);
        } catch (error) {
            this.showMessage('评论发布失败: ' + error.message, 'error');
        } finally {
            submitBtn.innerHTML = '发表评论';
            submitBtn.disabled = false;
        }
    }

    // 发布帖子
    async publishPost(title, content, type, imageFile) {
        const user = this.getUserInfo();
        const formData = new FormData();
        
        formData.append('title', title);
        formData.append('content', content);
        formData.append('author', user.username);
        formData.append('uid', user.uid);
        formData.append('type', type);
        formData.append('token', user.token);
        
        if (imageFile) {
            formData.append('image', imageFile);
        }

        const response = await fetch(`${this.baseUrl}/forum/save_post.php`, {
            method: 'POST',
            body: formData
        });

        if (!response.ok) {
            throw new Error(`HTTP ${response.status}`);
        }

        const data = await response.json();
        
        if (!data.success) {
            throw new Error(data.message || '发布失败');
        }

        return data;
    }

    // 发布评论
    async publishComment(postId, content) {
        const user = this.getUserInfo();
        const formData = new FormData();
        
        formData.append('post_id', postId);
        formData.append('content', content);
        formData.append('author', user.username);
        formData.append('uid', user.uid);
        formData.append('token', user.token);

        const response = await fetch(`${this.baseUrl}/forum/save_comment.php`, {
            method: 'POST',
            body: formData
        });

        if (!response.ok) {
            throw new Error(`HTTP ${response.status}`);
        }

        const data = await response.json();
        
        if (!data.success) {
            throw new Error(data.message || '评论发布失败');
        }

        return data;
    }

    // 点赞帖子
    async likePost(postId) {
        if (!this.isLoggedIn()) {
            this.showMessage('请先登录', 'error');
            return;
        }

        try {
            const user = this.getUserInfo();
            const formData = new FormData();
            
            formData.append('post_id', postId);
            formData.append('uid', user.uid);
            formData.append('token', user.token);

            const response = await fetch(`${this.baseUrl}/forum/like_post.php`, {
                method: 'POST',
                body: formData
            });

            if (!response.ok) {
                throw new Error(`HTTP ${response.status}`);
            }

            const data = await response.json();
            
            if (data.success) {
                // 更新本地点赞状态
                if (this.userLikes.has(postId)) {
                    this.userLikes.delete(postId);
                } else {
                    this.userLikes.add(postId);
                }
                this.saveUserLikes();
                
                // 重新加载帖子更新点赞数
                this.loadPosts();
                this.showMessage(data.message);
            } else {
                throw new Error(data.message || '点赞失败');
            }
        } catch (error) {
            this.showMessage('点赞失败: ' + error.message, 'error');
        }
    }

    // 重置表单
    resetPostForm() {
        document.getElementById('postTitle').value = '';
        document.getElementById('postContent').value = '';
        
        const imageInput = document.getElementById('imageInput');
        const imagePreview = document.getElementById('imagePreview');
        const fileName = document.getElementById('fileName');
        const imageUploadSection = document.getElementById('imageUploadSection');
        
        if (imageInput) imageInput.value = '';
        if (imagePreview) {
            imagePreview.src = '';
            imagePreview.style.display = 'none';
        }
        if (fileName) fileName.textContent = '';
        if (imageUploadSection) imageUploadSection.style.display = 'none';
        
        this.currentPostType = 'text';
        this.currentImageFile = null;
        
        document.querySelectorAll('.post-type').forEach(t => t.classList.remove('active'));
        document.querySelector('.post-type[data-type="text"]').classList.add('active');
    }

    // 加载帖子
    async loadPosts() {
        const postsContainer = document.getElementById('postsContainer');
        if (!postsContainer) return;

        try {
            postsContainer.innerHTML = '<div class="card loading"><i class="fas fa-spinner fa-spin"></i> 加载中...</div>';
            
            const response = await fetch(`${this.baseUrl}/forum/get_posts.php`);
            if (!response.ok) {
                throw new Error(`HTTP ${response.status}`);
            }
            
            const data = await response.json();
            
            if (data.success) {
                this.renderPosts(data.posts || []);
            } else {
                throw new Error(data.message || '加载失败');
            }
        } catch (error) {
            postsContainer.innerHTML = `
                <div class="card error">
                    <i class="fas fa-exclamation-triangle"></i>
                    <p>加载失败: ${error.message}</p>
                    <button onclick="forum.loadPosts()" class="btn btn-primary">
                        <i class="fas fa-redo"></i> 重新加载
                    </button>
                </div>
            `;
        }
    }

    // 加载评论
    async loadComments(postId) {
        const commentsList = document.getElementById('commentsList');
        if (!commentsList) return;

        try {
            commentsList.innerHTML = '<div class="loading"><i class="fas fa-spinner fa-spin"></i> 加载评论中...</div>';
            
            const response = await fetch(`${this.baseUrl}/forum/get_comments.php?post_id=${postId}`);
            if (!response.ok) {
                throw new Error(`HTTP ${response.status}`);
            }
            
            const data = await response.json();
            
            if (data.success) {
                this.renderComments(data.comments || []);
            } else {
                throw new Error(data.message || '加载评论失败');
            }
        } catch (error) {
            commentsList.innerHTML = `<div class="error">加载评论失败: ${error.message}</div>`;
        }
    }

    // 渲染帖子 - 修复内容显示问题
    renderPosts(posts) {
        const postsContainer = document.getElementById('postsContainer');
        if (!postsContainer) return;

        if (!posts || posts.length === 0) {
            postsContainer.innerHTML = `
                <div class="card">
                    <div style="text-align: center; padding: 2rem;">
                        <i class="fas fa-inbox" style="font-size: 3rem; opacity: 0.3;"></i>
                        <p style="margin-top: 1rem;">还没有帖子，快来发布第一个吧！</p>
                    </div>
                </div>
            `;
            return;
        }

        let html = '';
        posts.forEach(post => {
            const currentUid = localStorage.getItem('uid');
            const isAuthor = currentUid === post.uid;
            const canDelete = isAuthor || this.isAdmin;
            const isLiked = this.userLikes.has(post.id.toString());
            
            const deleteBtn = canDelete ? 
                `<div class="post-action" onclick="forum.deletePost(${post.id})">
                    <i class="fas fa-trash"></i> 删除
                    ${this.isAdmin && !isAuthor ? ' <small>(管理)</small>' : ''}
                </div>` : '';
            
            const imageHtml = post.image ? 
                `<img src="${post.image}" class="post-image" onclick="forum.viewImage('${post.image}')">` : '';
            
            // 修复：直接使用post.content，不进行HTML转义
            const postContent = post.content || '';
            
            html += `
                <div class="card post" data-post-id="${post.id}">
                    <div class="post-header">
                        <div class="post-author">
                            <i class="fas fa-user-circle"></i> ${this.escapeHtml(post.author || '')}
                            <span style="font-size: 0.8rem; opacity: 0.7;">(#${post.uid || ''})</span>
                            ${this.isAdmin ? ' <span style="color: #ff4d4f; font-size: 0.7rem;">[管理员]</span>' : ''}
                        </div>
                        <div class="post-date">${post.date || ''}</div>
                    </div>
                    <div class="post-title">${this.escapeHtml(post.title || '')}</div>
                    <div class="post-content">${this.formatContent(postContent)}</div>
                    ${imageHtml}
                    <div class="post-footer">
                        <div class="post-action ${isLiked ? 'liked' : ''}" onclick="forum.likePost(${post.id})">
                            <i class="fas fa-heart"></i> 
                            <span class="like-count">${post.likes || 0}</span>
                        </div>
                        <div class="post-action commented" onclick="forum.openCommentModal(${post.id}, '${this.escapeHtml(post.title || '')}', '${this.escapeHtml(post.author || '')}')">
                            <i class="fas fa-comment"></i> 
                            <span class="comment-count">${post.comments || 0}</span>
                        </div>
                        <div class="post-action"><i class="fas fa-share"></i> 分享</div>
                        ${deleteBtn}
                    </div>
                </div>
            `;
        });
        
        postsContainer.innerHTML = html;
    }

    // 渲染评论 - 修复内容显示问题
    renderComments(comments) {
        const commentsList = document.getElementById('commentsList');
        if (!commentsList) return;

        if (!comments || comments.length === 0) {
            commentsList.innerHTML = '<div class="no-comments">暂无评论</div>';
            return;
        }

        let html = '';
        comments.forEach(comment => {
            // 修复：直接使用comment.content，不进行HTML转义
            const commentContent = comment.content || '';
            
            html += `
                <div class="comment">
                    <div class="comment-header">
                        <div class="comment-author">
                            <i class="fas fa-user"></i> ${this.escapeHtml(comment.author || '')}
                            <span style="font-size: 0.7rem; opacity: 0.7;">(#${comment.uid || ''})</span>
                        </div>
                        <div class="comment-date">${comment.date || ''}</div>
                    </div>
                    <div class="comment-content">${this.formatContent(commentContent)}</div>
                </div>
            `;
        });
        
        commentsList.innerHTML = html;
    }

    // 删除帖子
    async deletePost(postId) {
        if (!confirm('确定要删除这个帖子吗？此操作不可撤销。')) {
            return;
        }

        try {
            const user = this.getUserInfo();
            const formData = new FormData();
            formData.append('post_id', postId);
            formData.append('uid', user.uid);
            formData.append('token', user.token);

            const response = await fetch(`${this.baseUrl}/forum/delete_post.php`, {
                method: 'POST',
                body: formData
            });

            const data = await response.json();
            
            if (data.success) {
                this.showMessage('帖子删除成功');
                this.loadPosts();
            } else {
                throw new Error(data.message || '删除失败');
            }
        } catch (error) {
            this.showMessage('删除失败: ' + error.message, 'error');
        }
    }

    // 查看图片
    viewImage(imageUrl) {
        const overlay = document.createElement('div');
        overlay.style.cssText = `
            position: fixed; top: 0; left: 0; width: 100%; height: 100%;
            background: rgba(0,0,0,0.9); display: flex; align-items: center;
            justify-content: center; z-index: 10000; cursor: zoom-out;
        `;
        
        const img = document.createElement('img');
        img.src = imageUrl;
        img.style.cssText = `
            max-width: 95%; max-height: 95%; border-radius: 8px;
            box-shadow: 0 0 50px rgba(0,0,0,0.5);
        `;
        
        overlay.appendChild(img);
        overlay.addEventListener('click', () => overlay.remove());
        document.body.appendChild(overlay);
    }

    // 工具函数 - 修复HTML转义问题
    escapeHtml(text) {
        if (!text) return '';
        const div = document.createElement('div');
        div.textContent = text;
        return div.innerHTML;
    }

    // 格式化内容 - 修复换行显示问题
    formatContent(content) {
        if (!content) return '';
        // 先进行基本的HTML转义
        const escaped = this.escapeHtml(content);
        // 然后将换行符转换为<br>标签
        return escaped.replace(/\n/g, '<br>');
    }
}

// 创建全局实例
const forum = new ForumManager();

// 页面加载完成后初始化
document.addEventListener('DOMContentLoaded', function() {
    forum.init();
});