import React, { useCallback, useEffect, useState } from 'react'
import { useFormikContext, Field } from 'formik'
import AddressBookNew from './AddressBookNew'
import SwitchTimeSlotFreePicker from './SwitchTimeSlotFreePicker'
import { Input, Button } from 'antd'
import DateRangePicker from './DateRangePicker'
import Packages from './Packages'
import { useTranslation } from 'react-i18next'
import TotalWeight from './TotalWeight'
import Spinner from '../core/Spinner'
import TimeSlotPicker from './TimeSlotPicker'

import './Task.scss'
import TagsSelect from '../TagsSelect'

export default ({
  addresses,
  storeId,
  index,
  storeDeliveryInfos,
  deliveryId,
  onAdd,
  dropoffSchema,
  onRemove,
  showRemoveButton,
  showAddButton,
  packages,
  isAdmin,
  tags,
}) => {
  const { t } = useTranslation()

  const { values, setFieldValue } = useFormikContext()
  const task = values.tasks[index]

  const format = 'LL'

  const [showLess, setShowLess] = useState(false)
  const [isTimeSlotSelect, setIsTimeSlotSelect] = useState(true)

  useEffect(() => {
    if (
      isTimeSlotSelect &&
      storeDeliveryInfos.timeSlots?.length > 0 &&
      !deliveryId
    ) {
      setFieldValue(`tasks[${index}].after`, null)
      setFieldValue(`tasks[${index}].before`, null)
    } else {
      setFieldValue(`tasks[${index}].timeSlot`, null)
    }
  }, [isTimeSlotSelect, storeDeliveryInfos])

  useEffect(() => {
    const shouldShowLess =
      task.type === 'DROPOFF' &&
      values.tasks.length > 2 &&
      index !== values.tasks.length - 1
    setShowLess(shouldShowLess)
  }, [task.type, values.tasks.length, index])

  const areDefinedTimeSlots = useCallback(() => {
    return (
      storeDeliveryInfos &&
      Array.isArray(storeDeliveryInfos.timeSlots) &&
      storeDeliveryInfos.timeSlots.length > 0
    )
  }, [storeDeliveryInfos])

  return (
    <div className="task border p-4 mb-4">
      <div
        className={
          task.type === 'PICKUP'
            ? 'task__header task__header--pickup'
            : 'task__header task__header--dropoff'
        }>
        {task.type === 'PICKUP' ? (
          <i className="fa fa-arrow-up"></i>
        ) : (
          <i className="fa fa-arrow-down"></i>
        )}
        <h3 className="task__header__title mb-4">
          {task.type === 'PICKUP'
            ? t('DELIVERY_FORM_PICKUP_INFORMATIONS')
            : t('DELIVERY_FORM_DROPOFF_INFORMATIONS')}
        </h3>

        <button type="button" className="task__button">
          <i
            className={!showLess ? 'fa fa-chevron-up' : 'fa fa-chevron-down'}
            title={
              showLess
                ? t('DELIVERY_FORM_SHOW_MORE')
                : t('DELIVERY_FORM_SHOW_LESS')
            }
            onClick={() => setShowLess(!showLess)}></i>
        </button>
      </div>

      <div
        className={!showLess ? 'task__body' : 'task__body task__body--hidden'}>
        <AddressBookNew addresses={addresses} index={index} />

        {/* Spinner is used to avoid double renders. We wait for storeDeliveryInfos. It avoids to have double values : timeslots and after/before */}
        {isAdmin ? (
          storeDeliveryInfos.timeSlots ? (
            areDefinedTimeSlots() & !deliveryId ? (
              <SwitchTimeSlotFreePicker
                storeId={storeId}
                storeDeliveryInfos={storeDeliveryInfos}
                index={index}
                format={format}
                deliveryId={deliveryId}
                isTimeSlotSelect={isTimeSlotSelect}
                setIsTimeSlotSelect={setIsTimeSlotSelect}
              />
            ) : (
              <DateRangePicker
                format={format}
                index={index}
                isAdmin={isAdmin}
              />
            )
          ) : (
            <Spinner />
          ) // case store
        ) : storeDeliveryInfos.timeSlots ? (
          areDefinedTimeSlots() & !deliveryId ? (
            <TimeSlotPicker
              storeId={storeId}
              storeDeliveryInfos={storeDeliveryInfos}
              index={index}
            />
          ) : (
            <DateRangePicker format={format} index={index} />
          )
        ) : (
          <Spinner />
        )}

        {task.type === 'DROPOFF' ? (
          <div className="mt-4">
            {packages ? (
              <Packages
                storeId={storeId}
                index={index}
                packages={packages}
                deliveryId={deliveryId}
              />
            ) : null}
            <TotalWeight index={index} deliveryId={deliveryId} />
          </div>
        ) : null}

        <div className="mt-4 mb-4">
          <label
            htmlFor={`tasks[${index}].comments`}
            className="block mb-2 font-weight-bold">
            {t('ADMIN_DASHBOARD_TASK_FORM_COMMENTS_LABEL')}
          </label>
          <Field
            as={Input.TextArea}
            name={`tasks[${index}].comments`}
            placeholder={t('ADMIN_DASHBOARD_TASK_FORM_COMMENTS_PLACEHOLDER')}
            rows={4}
            style={{ resize: 'none' }}
          />
        </div>

        <div className="mt-4 mb-4">
          <div className="tags__title block mb-2 font-weight-bold">Tags</div>
          <TagsSelect
            tags={tags}
            defaultValue={values.tasks[index].tags || []}
            onChange={values => {
              const tags = values.map(tag => tag.value)
              setFieldValue(`tasks[${index}].tags`, tags)
            }}
          />
        </div>
      </div>
      {task.type === 'DROPOFF' && (
        <div className={!showLess ? 'task__footer' : 'task__footer--hidden'}>
          {showRemoveButton && (
            <Button
              onClick={() => onRemove(index)}
              type="button"
              className="mb-4">
              {t('DELIVERY_FORM_REMOVE_DROPOFF')}
            </Button>
          )}
          {showAddButton && (
            <div
              className="mb-4"
              style={{
                display: 'flex',
                justifyContent: 'space-between',
                alignItems: 'center',
              }}>
              <p>{t('DELIVERY_FORM_MULTIDROPOFF')}</p>
              <Button
                disabled={false}
                onClick={() => {
                  onAdd(dropoffSchema)
                }}>
                {t('DELIVERY_FORM_ADD_DROPOFF')}
              </Button>
            </div>
          )}
        </div>
      )}
    </div>
  )
}
