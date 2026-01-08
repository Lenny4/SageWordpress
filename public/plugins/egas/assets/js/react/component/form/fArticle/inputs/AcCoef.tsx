import * as React from "react";
import { useImperativeHandle, useRef } from "react";
import { Tooltip } from "@mui/material";
import { getTranslations } from "../../../../../functions/translations";
import {
  FormValidInterface,
  InputInterface,
  TriggerFormContentChanged,
} from "../../../../../interface/InputInterface";
import { handleChangeInputGeneric } from "../../../../../functions/form";
import { TOKEN } from "../../../../../token";
import { numberValidator } from "../../../../../functions/validator";

let translations: any = getTranslations();

export type AcCoefInputState = {
  defaultValue: number;
  arCoef: number | string;
  acCategorie: number | string;
  triggerFormContentChanged?: TriggerFormContentChanged;
};

type FormState = {
  acCoef: InputInterface;
  realAcCoef: InputInterface;
  valueLock: InputInterface;
};

export const AcCoefInput = React.forwardRef(
  (
    {
      defaultValue,
      arCoef,
      acCategorie,
      triggerFormContentChanged,
    }: AcCoefInputState,
    ref,
  ) => {
    const inputRef = useRef<any>(null);
    arCoef = Number(arCoef);

    const getDefaultValue = (): FormState => {
      const v = defaultValue ?? 0;
      const acCoef = Number(v) === 0 ? arCoef : Number(v);
      return {
        acCoef: { value: acCoef },
        realAcCoef: { value: v.toString() },
        valueLock: { value: acCoef > 0 && acCoef !== arCoef },
      };
    };
    const [values, setValues] = React.useState<FormState>(getDefaultValue());

    const handleChange =
      (prop: keyof FormState) =>
      (event: React.ChangeEvent<HTMLInputElement>) => {
        if (prop === "acCoef") {
          setValues((v) => {
            const newValue = Number(event.target.value);
            return {
              ...v,
              valueLock: {
                ...v.valueLock,
                value: newValue > 0 && newValue !== arCoef,
              },
            };
          });
        }
        handleChangeInputGeneric(event, prop, setValues);
      };

    const handleRealAcCoef = () => {
      setValues((v) => {
        return {
          ...v,
          realAcCoef: {
            ...v.realAcCoef,
            value:
              v.valueLock.value && arCoef !== Number(v.acCoef.value)
                ? Number(v.acCoef.value)
                : "0",
          },
        };
      });
    };

    const isValid = async () => {
      const error = await numberValidator({
        value: values.acCoef.value,
        canBeEmpty: true,
      });
      setValues((v) => {
        return {
          ...v,
          acCoef: {
            ...v.acCoef,
            error: error,
          },
        };
      });
      return error === "";
    };

    const resetAcCoef = () => {
      setValues((v) => {
        return {
          ...v,
          acCoef: {
            ...v.acCoef,
            value: arCoef,
          },
          valueLock: {
            ...v.valueLock,
            value: false,
          },
        };
      });
    };

    useImperativeHandle(ref, () => ({
      async isValid(): Promise<FormValidInterface> {
        const valid = await isValid();
        return {
          valid: valid,
          details: [
            {
              valid: valid,
              ref: ref,
              dRef: inputRef,
            },
          ],
        };
      },
    }));

    React.useEffect(() => {
      isValid();
    }, [values.acCoef.value]); // eslint-disable-line react-hooks/exhaustive-deps

    React.useEffect(() => {
      handleRealAcCoef();
    }, [values.acCoef.value]); // eslint-disable-line react-hooks/exhaustive-deps

    React.useEffect(() => {
      if (triggerFormContentChanged) {
        triggerFormContentChanged(
          `_${TOKEN}_fArtclients[${acCategorie}][acCoef]`,
          values.acCoef.value,
        );
      }
    }, [values.acCoef.value]);

    React.useEffect(() => {
      if (!values.valueLock.value) {
        resetAcCoef();
      }
    }, [arCoef]); // eslint-disable-line react-hooks/exhaustive-deps

    return (
      <>
        <label htmlFor={`_${TOKEN}_acCoef`}>
          <Tooltip title={"acCoef"} arrow placement="top">
            <span>{translations["fArticles"]["acCoef"]}</span>
          </Tooltip>
        </label>
        <div style={{ display: "flex", alignItems: "flex-start" }}>
          <div style={{ position: "relative", flex: 1 }}>
            <input
              id={`_${TOKEN}_fArtclients[${acCategorie}][acCoef]`}
              name={`_${TOKEN}_fArtclients[${acCategorie}][acCoef]`}
              type={"hidden"}
              value={values.realAcCoef.value}
            />
            <input
              type={"number"}
              value={values.acCoef.value}
              onChange={handleChange("acCoef")}
              style={{ width: "100%" }}
              ref={inputRef}
              onBlur={() => {
                if (Number(values.acCoef.value) === 0) {
                  resetAcCoef();
                }
              }}
            />
            {values.acCoef.error && (
              <div className={`${TOKEN}_error_field`}>
                {values.acCoef.error}
              </div>
            )}
          </div>
        </div>
      </>
    );
  },
);
