// 玩家信息检测脚本
// 文件名: player-detector.js
// 功能: 检测服务器在线玩家并显示详细信息

class PlayerDetector {
    constructor() {
        this.serverAddress = 'im.rainplay.cn';
        this.serverPort = 12159;
        this.apiEndpoint = 'https://api.mcsrvstat.us/2/';
        this.updateInterval = 45000; // 45秒更新一次
        this.timeout = 15000; // 15秒超时
        this.isChecking = false;
        this.lastUpdate = null;
        
        // 玩家数据缓存
        this.cachedPlayers = [];
        this.cacheExpiry = 60000; // 1分钟缓存
        
        this.init();
    }
    
    init() {
        console.log('玩家检测器初始化...');
        
        // 页面加载时立即检查一次
        this.checkPlayers();
        
        // 设置定时检查
        setInterval(() => {
            if (!this.isChecking) {
                this.checkPlayers();
            }
        }, this.updateInterval);
        
        console.log('玩家检测器已启动');
    }
    
    async checkPlayers() {
        if (this.isChecking) return;
        
        this.isChecking = true;
        
        try {
            // 使用Minecraft服务器查询API
            const status = await this.queryServerStatus();
            
            if (status.online && status.players && status.players.list) {
                this.updatePlayersInfo(status.players.list);
            } else {
                this.setOfflineInfo();
            }
        } catch (error) {
            console.error('玩家信息检测失败:', error);
            this.setOfflineInfo();
        } finally {
            this.isChecking = false;
            this.lastUpdate = new Date();
        }
    }
    
    async queryServerStatus() {
        return new Promise((resolve, reject) => {
            const timeoutId = setTimeout(() => {
                reject(new Error('请求超时'));
            }, this.timeout);
            
            // 使用Minecraft服务器状态API
            fetch(`${this.apiEndpoint}${this.serverAddress}:${this.serverPort}`)
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
    
    updatePlayersInfo(playersList) {
        const playersContainer = document.getElementById('playersContainer');
        const playersGrid = document.getElementById('playersGrid');
        const playersCount = document.getElementById('playersCount');
        
        if (!playersContainer || !playersGrid || !playersCount) return;
        
        // 显示玩家容器
        playersContainer.style.display = 'block';
        playersCount.textContent = `${playersList.length} 位玩家`;
        
        // 清空现有玩家卡片
        playersGrid.innerHTML = '';
        
        if (playersList.length === 0) {
            const emptyMessage = document.createElement('div');
            emptyMessage.style.cssText = 'text-align: center; padding: 2rem; opacity: 0.7;';
            emptyMessage.textContent = '当前没有玩家在线';
            playersGrid.appendChild(emptyMessage);
            return;
        }
        
        // 创建玩家卡片
        playersList.forEach((player, index) => {
            const playerCard = document.createElement('div');
            playerCard.className = 'player-card';
            
            const avatar = document.createElement('div');
            avatar.className = 'player-avatar-large';
            avatar.textContent = player.charAt(0).toUpperCase();
            
            // 为不同玩家生成不同的背景色
            const hue = (index * 137) % 360;
            avatar.style.background = `hsl(${hue}, 70%, 60%)`;
            
            const name = document.createElement('div');
            name.className = 'player-name';
            name.textContent = player;
            
            playerCard.appendChild(avatar);
            playerCard.appendChild(name);
            
            playersGrid.appendChild(playerCard);
        });
        
        // 缓存玩家数据
        this.cachedPlayers = playersList;
    }
    
    setOfflineInfo() {
        const playersContainer = document.getElementById('playersContainer');
        const playersGrid = document.getElementById('playersGrid');
        const playersCount = document.getElementById('playersCount');
        
        if (playersContainer) playersContainer.style.display = 'none';
        if (playersCount) playersCount.textContent = '0 位玩家';
        if (playersGrid) playersGrid.innerHTML = '';
        
        this.cachedPlayers = [];
    }
    
    // 获取最后更新时间
    getLastUpdate() {
        return this.lastUpdate;
    }
    
    // 获取缓存的玩家数据
    getCachedPlayers() {
        return this.cachedPlayers;
    }
}

// 页面加载完成后初始化
document.addEventListener('DOMContentLoaded', function() {
    try {
        window.playerDetector = new PlayerDetector();
        console.log('玩家检测器已启动');
    } catch (error) {
        console.error('玩家检测器初始化失败:', error);
    }
});

// 提供全局函数供HTML调用
function checkPlayers() {
    if (window.playerDetector) {
        window.playerDetector.checkPlayers();
    }
}

// 导出供其他脚本使用
if (typeof module !== 'undefined' && module.exports) {
    module.exports = { PlayerDetector, checkPlayers };
}