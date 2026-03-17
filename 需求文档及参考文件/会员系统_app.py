# -*- coding: utf-8 -*-
from flask import Flask, render_template, request, jsonify
from flask_cors import CORS
import mysql.connector
from mysql.connector import pooling
from datetime import datetime, timedelta, date
from decimal import Decimal, InvalidOperation
import re
import requests

app = Flask(__name__)
CORS(app)


# --- 会员储值管理系统 ---

# --- 辅助函数：记录会员操作日志 (menmberdetail 和 menmberdetail_log) ---
def log_member_operation(cursor, fmode, fmemberid, fmembername, fclassesid, fclassesname, famount, fbalance, fmark="", fgoods=""):
    current_time = datetime.now()
    # 插入到主明细表
    detail_sql = """
        INSERT INTO menmberdetail (fdate, fmode, fmemberid, fmembername, fclassesid, fclassesname, fgoods, famount, fbalance, fmark)
        VALUES (%s, %s, %s, %s, %s, %s, %s, %s, %s, %s)
    """
    # 插入到日志表
    log_sql = """
        INSERT INTO menmberdetail_log (fdate, fmode, fmemberid, fmembername, fclassesid, fclassesname, fgoods, famount, fbalance, fmark)
        VALUES (%s, %s, %s, %s, %s, %s, %s, %s, %s, %s)
    """
    common_values = (current_time, fmode, fmemberid, fmembername, fclassesid, fclassesname, fgoods, famount, fbalance, fmark)

    try:
        cursor.execute(detail_sql, common_values)
    except mysql.connector.Error as detail_err:
        print(f"ERROR: 记录会员操作到 menmberdetail 失败 ({fmode}): {detail_err}")
        raise # Re-raise to allow transaction rollback

    try:
        # 使用相同的 current_time 和数据写入日志表
        cursor.execute(log_sql, common_values)
    except mysql.connector.Error as log_err:
        print(f"ERROR: 记录会员操作到 menmberdetail_log 失败 ({fmode}): {log_err}")
        # 根据策略，如果日志记录至关重要，也应抛出错误以回滚事务
        # raise log_err 


# --- 辅助函数：直接记录到 menmberdetail_log ---
# 用于记录明细修改和删除等不直接通过 log_member_operation 的操作
def log_to_menmberdetail_log_table(cursor, fmode, fmemberid, fmembername, fclassesid, fclassesname, fgoods, famount, fbalance, fmark):
    log_entry_fdate = datetime.now() # 日志记录操作的时间
    log_sql = """
        INSERT INTO menmberdetail_log (fdate, fmode, fmemberid, fmembername, fclassesid, fclassesname, fgoods, famount, fbalance, fmark)
        VALUES (%s, %s, %s, %s, %s, %s, %s, %s, %s, %s)
    """
    try:
        cursor.execute(log_sql, (log_entry_fdate, fmode, fmemberid, fmembername, fclassesid, fclassesname, fgoods, famount, fbalance, fmark))
    except mysql.connector.Error as log_err:
        print(f"ERROR: 直接记录到 menmberdetail_log 失败 ({fmode}): {log_err}")
        # 根据策略，也可能需要抛出错误
        # raise log_err


@app.route('/mem')
def membership_home():
    return render_template('membership.html')

@app.route('/membership/api/classes', methods=['GET'])
def get_member_classes():
    connection = get_db_connection()
    if not connection: return jsonify({'error': '数据库连接失败'}), 500
    cursor = None
    try:
        cursor = connection.cursor(dictionary=True)
        cursor.execute("SELECT fid, fname, foff FROM classes ORDER BY fid ASC")
        classes = cursor.fetchall()
        return jsonify(classes)
    except mysql.connector.Error as err:
        print(f"ERROR: 查询会员等级错误: {err}") # 保留错误日志
        return jsonify({'error': f'查询会员等级错误: {err}'}), 500
    finally:
        if cursor: cursor.close()
        if connection and connection.is_connected(): connection.close()

@app.route('/membership/api/members', methods=['GET'])
def get_members():
    connection = get_db_connection()
    if not connection: return jsonify({'error': '数据库连接失败'}), 500
    cursor = None
    try:
        class_id_filter = request.args.get('class_id')
        search_term_filter = request.args.get('search_term', '').strip()
        # 从member表查询时包含 fmark 字段
        query_str = "SELECT fid, fnumber, fname, fclassesid, fclassesname, faccruedamount, fbalance, fmark FROM member WHERE 1=1"
        params_list = []
        if class_id_filter and class_id_filter != "all" and class_id_filter.isdigit():
            query_str += " AND fclassesid = %s"
            params_list.append(int(class_id_filter))
        if search_term_filter:
            # 同时在 fnumber 和 fname 中搜索
            query_str += " AND (fnumber LIKE %s OR fname LIKE %s)"
            params_list.append(f"%{search_term_filter}%")
            params_list.append(f"%{search_term_filter}%")
        query_str += " ORDER BY fid DESC" # 通常按ID降序显示最新添加的会员
        cursor = connection.cursor(dictionary=True)
        cursor.execute(query_str, tuple(params_list))
        members_list = cursor.fetchall()
        return jsonify(members_list)
    except mysql.connector.Error as err:
        print(f"ERROR: 查询会员列表错误: {err}") # 保留错误日志
        return jsonify({'error': f'查询会员列表错误: {err}'}), 500
    finally:
        if cursor: cursor.close()
        if connection and connection.is_connected(): connection.close()

@app.route('/membership/api/member', methods=['POST'])
def add_member():
    connection = get_db_connection()
    if not connection: return jsonify({'error': '数据库连接失败'}), 500
    cursor = None
    try:
        data_payload = request.json
        fnumber_val = data_payload.get('fnumber', '').strip()
        fname_val = data_payload.get('fname', '').strip()    
        fclassesid_val = data_payload.get('fclassesid')
        fclassesname_val = data_payload.get('fclassesname', '').strip() # 前端应提供等级名称
        recharge_amount_str_val = data_payload.get('famount')
        fmark_val = data_payload.get('fmark', '').strip() # 获取会员备注

        if not all([fnumber_val, fname_val, fclassesid_val, fclassesname_val, recharge_amount_str_val]):
            return jsonify({'error': '会员编号、名称、等级、充值金额不能为空'}), 400
        try:
            recharge_amount_decimal_val = Decimal(str(recharge_amount_str_val))
            if recharge_amount_decimal_val <= Decimal('0'): # 初始充值金额应为正数
                return jsonify({'error': '充值金额必须为正数'}), 400
        except InvalidOperation:
            return jsonify({'error': '充值金额格式无效'}), 400
        
        cursor = connection.cursor(dictionary=True)
        # 检查会员编号是否已存在
        cursor.execute("SELECT fid FROM member WHERE fnumber = %s", (fnumber_val,))
        if cursor.fetchone():
            return jsonify({'error': f'会员编号 {fnumber_val} 已存在'}), 409 # 409 Conflict
        
        # 插入新会员记录，包括 fmark
        insert_sql_query = "INSERT INTO member (fnumber, fname, fclassesid, fclassesname, faccruedamount, fbalance, fmark) VALUES (%s, %s, %s, %s, %s, %s, %s)"
        cursor.execute(insert_sql_query, (fnumber_val, fname_val, fclassesid_val, fclassesname_val, recharge_amount_decimal_val, recharge_amount_decimal_val, fmark_val))
        new_member_id_val = cursor.lastrowid
        
        # 记录会员新增操作到明细表和日志表
        log_member_operation(cursor, '新增', new_member_id_val, fname_val, fclassesid_val, fclassesname_val, recharge_amount_decimal_val, recharge_amount_decimal_val, fmark="新会员开卡")
        connection.commit()
        return jsonify({'message': '会员新增成功', 'member_id': new_member_id_val}), 201
    except Exception as e:
        if connection: connection.rollback()
        print(f"ERROR: 新增会员时发生错误: {e}") # 保留错误日志
        return jsonify({'error': f'新增会员时发生错误: {e}'}), 500
    finally:
        if cursor: cursor.close()
        if connection and connection.is_connected(): connection.close()

@app.route('/membership/api/member/<int:member_id_param>', methods=['PUT'])
def update_member(member_id_param):
    connection = get_db_connection()
    if not connection: return jsonify({'error': '数据库连接失败'}), 500
    cursor = None
    try:
        data_payload = request.json
        fnumber_val = data_payload.get('fnumber', '').strip()
        fname_val = data_payload.get('fname', '').strip()    
        fclassesid_val = data_payload.get('fclassesid')
        fclassesname_val = data_payload.get('fclassesname', '').strip() # 前端应提供
        fbalance_str_val = data_payload.get('fbalance') # 允许直接修改余额
        fmark_member_val = data_payload.get('fmark', '').strip() # 获取会员备注

        if not all([fnumber_val, fname_val, fclassesid_val, fclassesname_val, fbalance_str_val]):
            return jsonify({'error': '会员编号、名称、等级、余额不能为空'}), 400
        try:
            fbalance_decimal_val = Decimal(str(fbalance_str_val))
            # 余额可以是0或负数，根据业务逻辑决定，这里不做 < 0 的限制
        except InvalidOperation:
            return jsonify({'error': '余额格式无效'}), 400
        
        cursor = connection.cursor(dictionary=True)
        # 检查修改后的会员编号是否已被其他会员使用
        cursor.execute("SELECT fid FROM member WHERE fnumber = %s AND fid != %s", (fnumber_val, member_id_param))
        if cursor.fetchone():
            return jsonify({'error': f'会员编号 {fnumber_val} 已被其他会员使用'}), 409
        
        # 获取旧数据用于比较和记录日志
        cursor.execute("SELECT faccruedamount, fbalance as old_fbalance, fname as old_fname, fclassesid as old_fclassesid, fclassesname as old_fclassesname, fmark as old_fmark FROM member WHERE fid = %s", (member_id_param,))
        old_member_data_dict = cursor.fetchone()
        if not old_member_data_dict:
            return jsonify({'error': '未找到指定会员'}), 404
        
        faccruedamount_val = old_member_data_dict['faccruedamount'] # 累计金额通常不在此处直接修改，除非特定业务

        # 构建修改日志的备注信息
        changed_fields_list = []
        if old_member_data_dict['old_fname'] != fname_val:
            changed_fields_list.append(f"名称从'{old_member_data_dict['old_fname']}'改为'{fname_val}'")
        if old_member_data_dict['old_fclassesid'] != int(fclassesid_val): # fclassesid 是 int
            changed_fields_list.append(f"等级从'{old_member_data_dict['old_fclassesname']}'改为'{fclassesname_val}'")
        if old_member_data_dict['old_fbalance'] != fbalance_decimal_val: # 余额是 Decimal
            changed_fields_list.append(f"余额从'{old_member_data_dict['old_fbalance']}'改为'{fbalance_decimal_val}'")
        if old_member_data_dict.get('old_fmark', '') != fmark_member_val: # 备注是字符串
            changed_fields_list.append("备注已修改")


        # 更新会员信息，包括fmark
        update_sql_query = "UPDATE member SET fnumber = %s, fname = %s, fclassesid = %s, fclassesname = %s, fbalance = %s, faccruedamount = %s, fmark = %s WHERE fid = %s"
        cursor.execute(update_sql_query, (fnumber_val, fname_val, fclassesid_val, fclassesname_val, fbalance_decimal_val, faccruedamount_val, fmark_member_val, member_id_param))
        
        # 准备日志操作备注
        operation_mark_log_val = fmark_member_val # 如果用户输入了备注，优先使用
        if not operation_mark_log_val and changed_fields_list: # 如果没有用户备注但有字段变更
            operation_mark_log_val = "修改信息: " + "; ".join(changed_fields_list)
        elif not operation_mark_log_val: # 没有任何备注信息
             operation_mark_log_val = "" # 或者 "无特定备注"

        # 记录会员修改操作，famount 通常为0，因为这不是一笔交易，而是信息变更
        # fbalance 记录的是修改后的会员表中的余额
        log_member_operation(cursor, '修改', member_id_param, fname_val, fclassesid_val, fclassesname_val, Decimal('0.00'), fbalance_decimal_val, fmark=operation_mark_log_val)
        connection.commit()
        
        # 返回更新后的会员完整信息
        cursor.execute("SELECT fid, fnumber, fname, fclassesid, fclassesname, faccruedamount, fbalance, fmark FROM member WHERE fid = %s", (member_id_param,))
        updated_member_dict = cursor.fetchone()
        return jsonify({'message': '会员信息更新成功', 'member': updated_member_dict})
    except Exception as e:
        if connection: connection.rollback()
        print(f"ERROR: 修改会员信息时发生错误: {e}") # 保留错误日志
        return jsonify({'error': f'修改会员信息时发生错误: {e}'}), 500
    finally:
        if cursor: cursor.close()
        if connection and connection.is_connected(): connection.close()

@app.route('/membership/api/member/<int:member_id_param>', methods=['DELETE'])
def delete_member(member_id_param):
    connection = get_db_connection()
    if not connection: return jsonify({'error': '数据库连接失败'}), 500
    cursor = None
    try:
        cursor = connection.cursor(dictionary=True)
        # 获取会员信息用于日志记录
        cursor.execute("SELECT fname, fclassesid, fclassesname, fbalance FROM member WHERE fid = %s", (member_id_param,))
        member_info_dict = cursor.fetchone()
        if not member_info_dict:
            return jsonify({'error': '未找到要删除的会员'}), 404

        # 记录删除操作到明细和日志，famount为0，fbalance记录删除前的余额
        log_member_operation(cursor, '删除', member_id_param, member_info_dict['fname'], member_info_dict['fclassesid'], member_info_dict['fclassesname'], Decimal('0.00'), member_info_dict['fbalance'], fmark="会员账户删除")
        
        # 执行删除
        cursor.execute("DELETE FROM member WHERE fid = %s", (member_id_param,))
        if cursor.rowcount == 0: # 如果没有行被删除 (可能已被并发删除)
             connection.rollback() # 回滚日志写入
             return jsonify({'error': '删除会员失败，会员可能已被删除'}), 404
        
        # 注意：这里没有删除关联的 menmberdetail 记录，按需决定是否要级联删除或处理
        connection.commit()
        return jsonify({'message': '会员删除成功'})
    except Exception as e:
        if connection: connection.rollback()
        print(f"ERROR: 删除会员时发生错误: {e}") # 保留错误日志
        return jsonify({'error': f'删除会员时发生错误: {e}'}), 500
    finally:
        if cursor: cursor.close()
        if connection and connection.is_connected(): connection.close()

@app.route('/membership/api/member/<int:member_id_param>/recharge', methods=['POST'])
def recharge_member(member_id_param):
    connection = get_db_connection()
    if not connection: return jsonify({'error': '数据库连接失败'}), 500
    cursor = None
    try:
        data_payload = request.json
        recharge_amount_str_val = data_payload.get('famount')
        fmark_operation_val = data_payload.get('fmark', '').strip() # 获取备注

        if not recharge_amount_str_val:
            return jsonify({'error': '充值金额不能为空'}), 400
        try:
            recharge_amount_decimal_val = Decimal(str(recharge_amount_str_val))
            if recharge_amount_decimal_val <= Decimal('0'):
                return jsonify({'error': '充值金额必须为正数'}), 400
        except InvalidOperation:
            return jsonify({'error': '充值金额格式无效'}), 400
        
        cursor = connection.cursor(dictionary=True)
        # 获取会员当前信息
        cursor.execute("SELECT fname, fclassesid, fclassesname, faccruedamount, fbalance FROM member WHERE fid = %s", (member_id_param,))
        member_info_dict = cursor.fetchone()
        if not member_info_dict:
            return jsonify({'error': '未找到指定会员'}), 404
        
        # 计算新的累计金额和余额
        new_accrued_amount_val = member_info_dict['faccruedamount'] + recharge_amount_decimal_val
        new_balance_val = member_info_dict['fbalance'] + recharge_amount_decimal_val

        # 处理充值时可能发生的等级变更
        updated_fclassesid_val = data_payload.get('fclassesid', member_info_dict['fclassesid'])
        updated_fclassesname_val = data_payload.get('fclassesname', '').strip()

        if 'fclassesid' in data_payload and not updated_fclassesname_val: # 如果前端传了等级ID但没传名称
             cursor.execute("SELECT fname FROM classes WHERE fid = %s", (updated_fclassesid_val,))
             class_info_dict = cursor.fetchone()
             if not class_info_dict:
                 return jsonify({'error': '提供的会员等级ID无效'}), 400
             updated_fclassesname_val = class_info_dict['fname'].strip() # 从数据库获取名称
        elif not 'fclassesid' in data_payload: # 如果前端完全没传等级信息，则使用旧等级
            updated_fclassesname_val = member_info_dict['fclassesname'] # 保持旧的等级名称
        
        # 更新会员表
        update_sql_query = "UPDATE member SET faccruedamount = %s, fbalance = %s, fclassesid = %s, fclassesname = %s WHERE fid = %s"
        cursor.execute(update_sql_query, (new_accrued_amount_val, new_balance_val, updated_fclassesid_val, updated_fclassesname_val, member_id_param))
        
        # 记录充值操作到明细和日志
        log_member_operation(cursor, '充值', member_id_param, member_info_dict['fname'], updated_fclassesid_val, updated_fclassesname_val, recharge_amount_decimal_val, new_balance_val, fmark=fmark_operation_val)
        connection.commit()
        
        # 返回更新后的会员完整信息 (包括fmark)
        cursor.execute("SELECT fid, fnumber, fname, fclassesid, fclassesname, faccruedamount, fbalance, fmark FROM member WHERE fid = %s", (member_id_param,))
        updated_member_dict = cursor.fetchone()
        return jsonify({'message': '充值成功', 'member': updated_member_dict})
    except Exception as e:
        if connection: connection.rollback()
        print(f"ERROR: 会员充值时发生错误: {e}") # 保留错误日志
        # import traceback; traceback.print_exc() # 生产环境中可以考虑移除或替换为更结构化的日志
        return jsonify({'error': f'会员充值时发生错误: {e}'}), 500
    finally:
        if cursor: cursor.close()
        if connection and connection.is_connected(): connection.close()

@app.route('/membership/api/member/<int:member_id_param>/consume', methods=['POST'])
def consume_member(member_id_param):
    connection = get_db_connection()
    if not connection: return jsonify({'error': '数据库连接失败'}), 500
    cursor = None
    try:
        data_payload = request.json
        fgoods_val = data_payload.get('fgoods', '').strip()
        factual_amount_str_val = data_payload.get('factual_amount') # 实际支付/扣除金额
        fmark_operation_val = data_payload.get('fmark', '').strip() # 获取备注

        if not fgoods_val or not factual_amount_str_val: # 商品名称和实际金额为必填
            return jsonify({'error': '商品名称和实际金额不能为空'}), 400
        try:
            actual_amount_decimal_val = Decimal(str(factual_amount_str_val))
            if actual_amount_decimal_val <= Decimal('0'): # 消费金额应为正数
                return jsonify({'error': '实际消费金额必须为正数'}), 400
        except InvalidOperation:
            return jsonify({'error': '实际消费金额格式无效'}), 400
        
        cursor = connection.cursor(dictionary=True)
        # 获取会员信息
        cursor.execute("SELECT fname, fclassesid, fclassesname, fbalance FROM member WHERE fid = %s", (member_id_param,))
        member_info_dict = cursor.fetchone()
        if not member_info_dict:
            return jsonify({'error': '未找到指定会员'}), 404
        
        # 检查余额是否充足
        if member_info_dict['fbalance'] < actual_amount_decimal_val:
            return jsonify({'error': f'余额不足，当前余额: {member_info_dict["fbalance"]:.2f}'}), 400 # 400 Bad Request 或 402 Payment Required
        
        # 计算消费后余额
        new_balance_val = member_info_dict['fbalance'] - actual_amount_decimal_val
        # 更新会员余额 (注意：消费通常不影响累计充值金额 faccruedamount)
        update_sql_query = "UPDATE member SET fbalance = %s WHERE fid = %s"
        cursor.execute(update_sql_query, (new_balance_val, member_id_param))
        
        # 记录消费操作到明细和日志
        log_member_operation(cursor, '消费', member_id_param, member_info_dict['fname'], member_info_dict['fclassesid'], member_info_dict['fclassesname'], actual_amount_decimal_val, new_balance_val, fmark=fmark_operation_val, fgoods=fgoods_val)
        connection.commit()
        
        # 返回更新后的会员完整信息 (包括fmark)
        cursor.execute("SELECT fid, fnumber, fname, fclassesid, fclassesname, faccruedamount, fbalance, fmark FROM member WHERE fid = %s", (member_id_param,))
        updated_member_dict = cursor.fetchone()
        return jsonify({'message': '消费成功', 'member': updated_member_dict})
    except mysql.connector.Error as db_err: # 更具体的数据库错误处理
        if connection: connection.rollback()
        if db_err.errno == 1406: # 数据过长错误
            print(f"ERROR: 数据库错误: 数据过长 - {db_err}") # 保留错误日志
            # 尝试从错误消息中提取列名
            column_name_match = re.search(r"Data too long for column '(\w+)'", str(db_err))
            column_name = column_name_match.group(1) if column_name_match else "某个字段"
            error_message = f"输入内容过长，无法保存到'{column_name}'字段。"
            return jsonify({'error': error_message}), 400
        print(f"ERROR: 会员消费时发生数据库错误: {db_err}") # 保留错误日志
        return jsonify({'error': f'数据库操作失败: {db_err}'}), 500
    except Exception as e:
        if connection: connection.rollback()
        print(f"ERROR: 会员消费时发生未知错误: {e}") # 保留错误日志
        return jsonify({'error': f'会员消费时发生错误: {e}'}), 500
    finally:
        if cursor: cursor.close()
        if connection and connection.is_connected(): connection.close()

@app.route('/membership/api/member_details', methods=['GET'])
def get_member_details_route():
    connection = get_db_connection()
    if not connection: return jsonify({'error': '数据库连接失败'}), 500
    cursor = None
    final_query_to_execute = "" # 初始化以备错误日志使用
    params_list_filters = []    # 初始化以备错误日志使用
    try:
        start_date_filter_str = request.args.get('start_date')
        end_date_filter_str = request.args.get('end_date')
        search_term_filter = request.args.get('search_term', '').strip() # 会员名称搜索
        member_id_filter_val = request.args.get('member_id') # 特定会员ID查询

        query_base = """
            SELECT fid, fdate, fmode, fmemberid, fmembername,
                   fclassesid, fclassesname, fgoods, famount, fbalance, fmark
            FROM menmberdetail WHERE 1=1
        """
        
        query_conditions_added_flag = False # 标记是否添加了任何筛选条件

        if member_id_filter_val and member_id_filter_val.isdigit():
            query_base += " AND fmemberid = %s"
            params_list_filters.append(int(member_id_filter_val))
            query_conditions_added_flag = True
        else: # 只有在没有特定会员ID时，日期和名称搜索才生效
            if start_date_filter_str:
                try:
                    # 仅验证格式，实际查询时数据库会处理
                    datetime.strptime(start_date_filter_str, '%Y-%m-%d') 
                    query_base += " AND DATE(fdate) >= %s" # 使用 DATE() 忽略时间部分
                    params_list_filters.append(start_date_filter_str)
                    query_conditions_added_flag = True
                except ValueError:
                    return jsonify({'error': '开始日期格式无效，请使用YYYY-MM-DD'}), 400
            if end_date_filter_str:
                try:
                    datetime.strptime(end_date_filter_str, '%Y-%m-%d')
                    query_base += " AND DATE(fdate) <= %s"
                    params_list_filters.append(end_date_filter_str)
                    query_conditions_added_flag = True
                except ValueError:
                    return jsonify({'error': '结束日期格式无效，请使用YYYY-MM-DD'}), 400
            
            if search_term_filter: # 按会员名称模糊搜索
                query_base += " AND fmembername LIKE %s"
                params_list_filters.append(f"%{search_term_filter}%")
                query_conditions_added_flag = True
        
        query_base += " ORDER BY fdate DESC, fid DESC" # 按日期和ID降序排序，最新的在前面
        
        # 如果没有添加任何筛选条件，则限制返回结果数量，防止数据量过大
        if not query_conditions_added_flag: 
            query_base += " LIMIT 200" # 默认最多显示200条
        
        final_query_to_execute = query_base
        cursor = connection.cursor(dictionary=True)
        cursor.execute(final_query_to_execute, tuple(params_list_filters))
        details_list = cursor.fetchall()

        # 格式化输出数据
        for detail_row_item in details_list:
            # 格式化日期时间
            if isinstance(detail_row_item.get('fdate'), datetime): 
                # 保留原始datetime对象给前端可能的高级用途（如果需要）
                # detail_row_item['fdate_orig_dt'] = detail_row_item['fdate'] 
                detail_row_item['fdate'] = detail_row_item['fdate'].strftime('%Y-%m-%d %H:%M:%S')
            elif isinstance(detail_row_item.get('fdate'), date): # 如果数据库存的是date类型
                detail_row_item['fdate'] = detail_row_item['fdate'].strftime('%Y-%m-%d')
            
            # 格式化Decimal字段为字符串，便于JSON序列化和前端显示
            if isinstance(detail_row_item.get('famount'), Decimal):
                detail_row_item['famount'] = str(detail_row_item['famount'])
            if isinstance(detail_row_item.get('fbalance'), Decimal):
                detail_row_item['fbalance'] = str(detail_row_item['fbalance'])
        
        return jsonify(details_list)
    except mysql.connector.Error as err:
        # 确保在错误日志中打印执行的SQL和参数
        query_for_logging = final_query_to_execute if 'final_query_to_execute' in locals() else 'Query not constructed'
        params_for_logging = params_list_filters if 'params_list_filters' in locals() else 'Params not constructed'
        print(f"ERROR: 数据库查询会员明细错误: {err}") # 保留错误日志
        print(f"Failed SQL query: {query_for_logging}") # 保留错误日志
        print(f"Failed parameters: {params_for_logging}") # 保留错误日志
        return jsonify({'error': f'查询会员明细错误: {err}'}), 500
    except Exception as e:
        print(f"ERROR: 查询会员明细时发生未知错误: {e}") # 保留错误日志
        return jsonify({'error': f'处理请求时发生错误: {e}'}), 500
    finally:
        if cursor: cursor.close()
        if connection and connection.is_connected(): connection.close()


@app.route('/membership/api/member_detail/<int:detail_id>', methods=['PUT'])
def update_member_detail(detail_id):
    connection = get_db_connection()
    if not connection: return jsonify({'error': '数据库连接失败'}), 500
    cursor = None
    try:
        data = request.json
        new_fgoods = data.get('fgoods', '').strip()
        new_famount_str = data.get('famount')
        new_fmark = data.get('fmark', '').strip()

        if new_famount_str is None: # 金额是必需的
            return jsonify({'error': '调整金额不能为空'}), 400
        try:
            new_famount_decimal = Decimal(str(new_famount_str))
            # 金额可以为0或负数，取决于业务逻辑，这里不做严格正数限制，除非特定要求
        except InvalidOperation:
            return jsonify({'error': '调整金额格式无效'}), 400

        cursor = connection.cursor(dictionary=True)
        # 获取原始明细信息，包括 fmode 以判断对会员总余额的影响类型
        cursor.execute("SELECT fmemberid, fmembername, fclassesid, fclassesname, famount as old_famount, fbalance as old_fbalance, fmode FROM menmberdetail WHERE fid = %s", (detail_id,))
        original_detail = cursor.fetchone()

        if not original_detail:
            return jsonify({'error': '未找到要修改的明细记录'}), 404

        old_amount = original_detail['old_famount'] # Decimal类型
        old_balance = original_detail['old_fbalance'] # Decimal类型
        member_id = original_detail['fmemberid']
        detail_fmode = original_detail['fmode'] # 例如 '充值', '消费', '新增'

        # 根据原始明细的 fmode 判断如何调整会员总余额
        if detail_fmode in ['新增', '充值']: # 这些操作增加了会员余额
            # 变化量 = 新金额 - 旧金额
            new_fbalance = old_balance - old_amount + new_famount_decimal
        elif detail_fmode == '消费': # 此操作减少了会员余额
            # 消费金额变化 = 新消费金额 - 旧消费金额
            # 对总余额的影响是负的这个变化量
            # 即 旧消费金额 - 新消费金额
            new_fbalance = old_balance + old_amount - new_famount_decimal
        # 计算此条明细记录自身的新余额
        # 这个余额是这条明细操作发生后的余额快照
        # new_fbalance = old_balance - old_famount + new_famount_decimal


        # 1. 记录到 menmberdetail_log (记录的是修改后的状态)
        log_to_menmberdetail_log_table(
            cursor,
            fmode='修改明细', # 特定的fmode表示这是一个对明细的修改操作日志
            fmemberid=member_id,
            fmembername=original_detail['fmembername'],
            fclassesid=original_detail['fclassesid'],
            fclassesname=original_detail['fclassesname'],
            fgoods=new_fgoods,
            famount=new_famount_decimal,
            fbalance=new_fbalance, # 使用新计算的此条明细的余额
            fmark=new_fmark
        )

        # 2. 更新 menmberdetail 表本身
        # fmode 保持不变，表示原始操作类型。fdate 更新为当前修改时间。
        current_time = datetime.now()
        update_detail_sql = """
            UPDATE menmberdetail
            SET fgoods = %s, famount = %s, fbalance = %s, fmark = %s, fdate = %s
            WHERE fid = %s
        """
        cursor.execute(update_detail_sql, (new_fgoods, new_famount_decimal, new_fbalance, new_fmark, current_time, detail_id))

        # 3. --- 同步调整会员总余额 (member.fbalance) ---
        delta_for_member_balance = Decimal('0.0') # 初始化余额变化量

        # 根据原始明细的 fmode 判断如何调整会员总余额
        if detail_fmode in ['新增', '充值']: # 这些操作增加了会员余额
            # 变化量 = 新金额 - 旧金额
            delta_for_member_balance = new_famount_decimal - old_amount
        elif detail_fmode == '消费': # 此操作减少了会员余额
            # 消费金额变化 = 新消费金额 - 旧消费金额
            # 对总余额的影响是负的这个变化量
            # 即 旧消费金额 - 新消费金额
            delta_for_member_balance = old_amount - new_famount_decimal
        # 其他 fmode (如 '修改', '删除'这些是会员记录本身的修改日志，其famount通常为0)
        # 如果它们的 famount 不为0且需要处理，需在此添加逻辑

        if delta_for_member_balance != Decimal('0.0'):
            cursor.execute("UPDATE member SET fbalance = fbalance + %s WHERE fid = %s",
                           (delta_for_member_balance, member_id))
        # --- 会员总余额调整完毕 ---

        connection.commit()
        return jsonify({'message': '明细记录修改成功，会员余额已同步更新'})

    except Exception as e:
        if connection: connection.rollback()
        print(f"ERROR: 修改会员明细时发生错误: {e}")
        # import traceback; traceback.print_exc(); # 调试时使用
        return jsonify({'error': f'修改明细时发生错误: {e}'}), 500
    finally:
        if cursor: cursor.close()
        if connection and connection.is_connected(): connection.close()


@app.route('/membership/api/member_detail/<int:detail_id>', methods=['DELETE'])
def delete_member_detail(detail_id):
    connection = get_db_connection()
    if not connection: return jsonify({'error': '数据库连接失败'}), 500
    cursor = None
    try:
        cursor = connection.cursor(dictionary=True)
        # 获取要删除的明细的当前状态，用于记录日志和计算会员余额调整
        cursor.execute("SELECT fid, fdate, fmode, fmemberid, fmembername, fclassesid, fclassesname, fgoods, famount, fbalance, fmark FROM menmberdetail WHERE fid = %s", (detail_id,))
        detail_to_delete = cursor.fetchone()

        if not detail_to_delete:
            return jsonify({'error': '未找到要删除的明细记录'}), 404

        deleted_famount = detail_to_delete['famount'] # Decimal
        member_id = detail_to_delete['fmemberid']
        detail_fmode = detail_to_delete['fmode']

        # 1. 记录到 menmberdetail_log (记录的是删除前的状态)
        log_to_menmberdetail_log_table(
            cursor,
            fmode='删除明细', # 特定的fmode
            fmemberid=member_id,
            fmembername=detail_to_delete['fmembername'],
            fclassesid=detail_to_delete['fclassesid'],
            fclassesname=detail_to_delete['fclassesname'],
            fgoods=detail_to_delete['fgoods'],
            famount=deleted_famount,
            fbalance=detail_to_delete['fbalance'], # 这是该条明细记录发生后的余额快照
            fmark=detail_to_delete['fmark']
        )

        # 2. 从 menmberdetail 表中删除
        cursor.execute("DELETE FROM menmberdetail WHERE fid = %s", (detail_id,))
        if cursor.rowcount == 0:
            # 如果没有行被删除（可能已被并发操作删除）
            connection.rollback() # 回滚日志写入（如果日志写入也应原子化）
            return jsonify({'error': '删除明细失败，记录可能已被删除'}), 404

        # 3. --- 同步调整会员总余额 (member.fbalance) ---
        balance_adjustment_for_member = Decimal('0.0') # 初始化余额调整量

        if detail_fmode in ['新增', '充值']: # 这些操作曾增加会员余额，删除它们意味着要减去相应金额
            balance_adjustment_for_member = -deleted_famount
        elif detail_fmode == '消费': # 此操作曾减少会员余额，删除它意味着要加回相应金额
            balance_adjustment_for_member = deleted_famount
        # 其他 fmode

        if balance_adjustment_for_member != Decimal('0.0'):
            cursor.execute("UPDATE member SET fbalance = fbalance + %s WHERE fid = %s",
                           (balance_adjustment_for_member, member_id))
        # --- 会员总余额调整完毕 ---

        connection.commit()
        return jsonify({'message': '明细记录删除成功，会员余额已同步更新'})

    except Exception as e:
        if connection: connection.rollback()
        print(f"ERROR: 删除会员明细时发生错误: {e}")
        # import traceback; traceback.print_exc(); # 调试时使用
        return jsonify({'error': f'删除明细时发生错误: {e}'}), 500
    finally:
        if cursor: cursor.close()
        if connection and connection.is_connected(): connection.close()

# --- 蜡烛日志 ---
@app.route('/lazlog')
def candle_logs_page():
    """渲染蜡烛日志的HTML页面。"""
    return render_template('candle_logs.html')

@app.route('/api/candle_logs', methods=['GET'])
def get_candle_logs_data():
    """提供蜡烛日志数据的API接口，支持过滤和默认返回最新300条。"""
    connection = get_db_connection()
    if not connection:
        return jsonify({'error': '数据库连接失败'}), 500
    
    cursor = None
    try:
        cursor = connection.cursor(dictionary=True)
        
        filters_clauses = []
        params = []
        
        allowed_filters = {
            "Customer_name": "Customer_name LIKE %s",
            "product_name": "product_name LIKE %s",
            "Start_date": "Start_date = %s", # 前端应确保发送 'YYYY-MM-DD' 格式
            "end_date": "end_date = %s",     # 前端应确保发送 'YYYY-MM-DD' 格式
            "day": "day = %s",
            "mark": "mark LIKE %s",
            "mode": "mode LIKE %s",
            "creatime_date": "DATE(creatime) = %s" # 用于按日期部分过滤 creatime
        }

        base_query = """
            SELECT 
                DATE_FORMAT(creatime, '%Y-%m-%d %H:%i:%S') as creatime,
                mark,
                Customer_name, 
                product_name, 
                DATE_FORMAT(Start_date, '%Y-%m-%d') as Start_date, 
                DATE_FORMAT(end_date, '%Y-%m-%d') as end_date, 
                day, 
                mode
            FROM laz_1_log
        """
        
        for key, sql_template in allowed_filters.items():
            user_value = request.args.get(key)
            if user_value: 
                if "LIKE" in sql_template:
                    filters_clauses.append(sql_template)
                    params.append(f"%{user_value}%")
                else:
                    filters_clauses.append(sql_template)
                    params.append(user_value)
        
        query = base_query
        if filters_clauses:
            query += " WHERE " + " AND ".join(filters_clauses)
        
        query += " ORDER BY laz_1_log.creatime DESC LIMIT 300"
        
        cursor.execute(query, tuple(params))
        logs = cursor.fetchall()
        return jsonify(logs)
        
    except mysql.connector.Error as err:
        error_message = err.msg if hasattr(err, "msg") else str(err)
        print(f"ERROR: 查询蜡烛日志错误: {error_message}, Query: {query if 'query' in locals() else 'Query not defined'}, Params: {params if 'params' in locals() else 'Params not defined'}")
        return jsonify({'error': f'查询蜡烛日志错误: {error_message}'}), 500
    finally:
        if cursor: cursor.close()
        if connection and connection.is_connected(): connection.close()

# --- 会员日志 ---
@app.route('/memlog')
def member_logs_page():
    """渲染会员日志的HTML页面。"""
    return render_template('member_logs.html')

@app.route('/api/member_logs', methods=['GET'])
def get_member_logs_data():
    """提供会员日志数据的API接口，支持过滤和默认返回最新300条。"""
    connection = get_db_connection()
    if not connection:
        return jsonify({'error': '数据库连接失败'}), 500
    
    cursor = None
    try:
        cursor = connection.cursor(dictionary=True)
        
        filters_clauses = []
        params = []

        allowed_filters = {
            "fmode": "fmode LIKE %s",
            "fmembername": "fmembername LIKE %s",
            "fclassesname": "fclassesname LIKE %s",
            "fgoods": "fgoods LIKE %s",
            "famount": "CAST(famount AS CHAR) = %s", 
            "fbalance": "CAST(fbalance AS CHAR) = %s",
            "fmark": "fmark LIKE %s",
            "fdate_date": "DATE(fdate) = %s" # 用于按日期部分过滤 fdate
        }

        base_query = """
            SELECT 
                DATE_FORMAT(fdate, '%Y-%m-%d %H:%i:%S') as fdate, 
                fmode, 
                fmembername, 
                fclassesname, 
                fgoods, 
                CAST(famount AS CHAR) as famount, 
                CAST(fbalance AS CHAR) as fbalance, 
                fmark 
            FROM menmberdetail_log
        """
        
        for key, sql_template in allowed_filters.items():
            user_value = request.args.get(key)
            if user_value:
                if "LIKE" in sql_template:
                    filters_clauses.append(sql_template)
                    params.append(f"%{user_value}%")
                else:
                    filters_clauses.append(sql_template)
                    params.append(user_value)

        query = base_query
        if filters_clauses:
            query += " WHERE " + " AND ".join(filters_clauses)
        
        query += " ORDER BY menmberdetail_log.fdate DESC LIMIT 300"

        cursor.execute(query, tuple(params))
        logs = cursor.fetchall()
        return jsonify(logs)

    except mysql.connector.Error as err:
        error_message = err.msg if hasattr(err, "msg") else str(err)
        print(f"ERROR: 查询会员日志错误: {error_message}, Query: {query if 'query' in locals() else 'Query not defined'}, Params: {params if 'params' in locals() else 'Params not defined'}")
        return jsonify({'error': f'查询会员日志错误: {error_message}'}), 500
    finally:
        if cursor: cursor.close()
        if connection and connection.is_connected(): connection.close()

if __name__ == '__main__':
    # debug=True 在开发时有用，生产环境通常设为False以提高性能和安全性
    app.run(host='0.0.0.0', port=5000, debug=False)