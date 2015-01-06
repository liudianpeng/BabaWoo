<?php

class Stop extends Eloquent {
	
	protected $fillable = array('name', 'latitude', 'longitude', 'stop_id');

	public function lines()
	{
		return $this->belongsToMany('Line')->withPivot('id', 'stop_no');
	}
	
	public function originLines()
	{
		return $this->hasMany('Line', 'origin_stop_id');
	}
	
	public function terminalLines()
	{
		return $this->hasMany('Line', 'terminal_stop_id');
	}
	
	/**
	 * 缩小范围，只搜索正负$scope经纬度范围内的车站
	 * 这可以用到latitude和longitude字段的索引，是快查询
	 * @param Query\Builder $query
	 * @param double $latitude 百度地图纬度
	 * @param double $longitude 百度地图经度
	 * @param float $scope 经纬度范围半径
	 */
	public function scopeNearBy($query, $latitude, $longitude, $scope = 0.01)
	{
		return $query->whereBetween('latitude', array($latitude - $scope, $latitude + $scope))
			->whereBetween('longitude', array($longitude - $scope, $longitude + $scope));
	}
	
	/**
	 * 根据经纬度差的平方和求得距离系数并排序
	 * @param Query\Builder $query
	 * @param double $latitude
	 * @param double $longitude
	 */
	public function scopeDistanceAscending($query, $latitude, $longitude)
	{
		return $query->orderByRaw('`latitude` AND `longitude` DESC, POW(`latitude` - ?, 2) + POW(`longitude` - ?, 2) ASC', array($latitude, $longitude));
	}
	
}
