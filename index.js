// 服务器状态检测脚本
// 文件: http://123.60.174.101:48053/index.js

class ServerStatusChecker {
    constructor() {
        this.serverAddress = 'im.rainplay.cn';
        this.serverPort = 12159;
        this.updateInterval = 30000; // 30秒更新一次
        this.timeout = 10000; // 10秒超时
        this.isChecking = false;
        
        this.init();
    }
    
    init() {
        // 页面加载时立即检查一次
        this.checkServerStatus();
        
        // 设置定时检查
        setInterval(() => {
            if (!this.isChecking) {
                this.checkServerStatus();
            }
        }, this.updateInterval);
        
        // 绑定刷新按钮事件
        const refreshBtn = document.querySelector('.status-refresh');
        if (refreshBtn) {
            refreshBtn.addEventListener('click', () => {
                this.checkServerStatus();
            });
        }
    }
    
    async checkServerStatus() {
        if (this.isChecking) return;
        
        this.isChecking = true;
        this.setStatus('loading', '检测中...');
        
        try {
            // 使用Minecraft服务器查询API
            const status = await this.queryServerStatus();
            
            if (status.online) {
                this.setStatus('online', '在线');
                this.updateServerInfo(status);
            } else {
                this.setStatus('offline', '离线');
                this.setOfflineInfo();
            }
        } catch (error) {
            console.error('服务器状态检测失败:', error);
            this.setStatus('offline', '离线');
            this.setOfflineInfo();
        } finally {
            this.isChecking = false;
        }
    }
    
    async queryServerStatus() {
        return new Promise((resolve, reject) => {
            const timeoutId = setTimeout(() => {
                reject(new Error('请求超时'));
            }, this.timeout);
            
            // 使用Minecraft服务器状态API
            // 注意：由于浏览器同源策略，这里需要使用代理或支持CORS的API
            fetch(`https://api.mcsrvstat.us/2/${this.serverAddress}:${this.serverPort}`)
                .then(response => {
                    clearTimeout(timeoutId);
                    if (!response.ok) {
                        throw new Error('网络请求失败');
                    }
                    return response.json();
                })
                .then(data => {
                    resolve(data);
                })
                .catch(error => {
                    clearTimeout(timeoutId);
                    reject(error);
                });
        });
    }
    
    setStatus(status, text) {
        const statusElement = document.getElementById('serverStatus');
        if (!statusElement) return;
        
        // 移除所有状态类
        statusElement.classList.remove('status-online', 'status-offline', 'status-loading');
        
        // 添加当前状态类
        statusElement.classList.add(`status-${status}`);
        
        // 更新状态文本
        const textElement = statusElement.querySelector('span');
        if (textElement) {
            textElement.textContent = text;
        }
    }
    
    updateServerInfo(status) {
        // 更新在线玩家数量
        const onlinePlayersElement = document.getElementById('onlinePlayers');
        const maxPlayersElement = document.getElementById('maxPlayers');
        
        if (onlinePlayersElement && status.players) {
            onlinePlayersElement.textContent = status.players.online || 0;
        }
        
        if (maxPlayersElement && status.players) {
            maxPlayersElement.textContent = status.players.max || 0;
        }
        
        // 更新服务器版本
        const versionElement = document.getElementById('serverVersion');
        if (versionElement && status.version) {
            versionElement.textContent = status.version;
        }
        
        // 更新延迟
        const pingElement = document.getElementById('serverPing');
        if (pingElement && status.debug) {
            pingElement.textContent = status.debug.ping ? `${status.debug.ping}ms` : '-';
        }
        
        // 更新玩家头像（如果有玩家在线）
        this.updatePlayerAvatars(status.players);
    }
    
    setOfflineInfo() {
        // 服务器离线时的默认值
        const onlinePlayersElement = document.getElementById('onlinePlayers');
        const maxPlayersElement = document.getElementById('maxPlayers');
        const pingElement = document.getElementById('serverPing');
        
        if (onlinePlayersElement) onlinePlayersElement.textContent = '0';
        if (maxPlayersElement) maxPlayersElement.textContent = '0';
        if (pingElement) pingElement.textContent = '-';
        
        // 清空玩家头像
        this.updatePlayerAvatars(null);
    }
    
    updatePlayerAvatars(players) {
        const avatarsContainer = document.getElementById('playerAvatars');
        if (!avatarsContainer) return;
        
        // 清空现有头像
        avatarsContainer.innerHTML = '';
        
        if (!players || !players.list || players.list.length === 0) {
            return;
        }
        
        // 只显示前5个玩家（避免UI过于拥挤）
        const displayPlayers = players.list.slice(0, 5);
        
        displayPlayers.forEach((player, index) => {
            const avatar = document.createElement('div');
            avatar.className = 'player-avatar';
            avatar.title = player;
            
            // 使用玩家名字的首字母作为头像内容
            const initial = player.charAt(0).toUpperCase();
            avatar.textContent = initial;
            
            // 为不同玩家生成不同的背景色
            const hue = (index * 137) % 360; // 使用黄金角度分布颜色
            avatar.style.background = `hsl(${hue}, 70%, 60%)`;
            
            avatarsContainer.appendChild(avatar);
        });
        
        // 如果玩家数量超过5个，显示更多指示器
        if (players.list.length > 5) {
            const moreIndicator = document.createElement('div');
            moreIndicator.className = 'player-avatar';
            moreIndicator.textContent = '+';
            moreIndicator.title = `还有 ${players.list.length - 5} 个玩家在线`;
            moreIndicator.style.background = 'var(--primary)';
            avatarsContainer.appendChild(moreIndicator);
        }
    }
}

// 备用检测方法（如果主API不可用）
class FallbackStatusChecker {
    async check() {
        try {
            // 尝试使用其他API端点
            const response = await fetch(`https://api.mcsrvstat.us/bedrock/2/${this.serverAddress}:${this.serverPort}`);
            if (response.ok) {
                return await response.json();
            }
        } catch (error) {
            // 如果所有API都失败，返回离线状态
            return { online: false };
        }
    }
}

// 页面加载完成后初始化
document.addEventListener('DOMContentLoaded', function() {
    // 等待一段时间确保其他资源加载完成
    setTimeout(() => {
        try {
            new ServerStatusChecker();
            console.log('服务器状态检测器已启动');
        } catch (error) {
            console.error('服务器状态检测器初始化失败:', error);
            
            // 如果初始化失败，设置为离线状态
            const statusElement = document.getElementById('serverStatus');
            if (statusElement) {
                statusElement.className = 'status-indicator status-offline';
                statusElement.innerHTML = '<div class="status-dot"></div><span>检测失败</span>';
            }
        }
    }, 1000);
});

// 提供全局函数供HTML调用
function checkServerStatus() {
    const checker = new ServerStatusChecker();
    checker.checkServerStatus();
}

// 导出供其他脚本使用
if (typeof module !== 'undefined' && module.exports) {
    module.exports = { ServerStatusChecker, checkServerStatus };
}