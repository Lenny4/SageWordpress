import * as React from "react";
import { useImperativeHandle } from "react";
import { IconButton, Tooltip } from "@mui/material";
import InfoIcon from "@mui/icons-material/Info";
import { getTranslations } from "../../../../../functions/translations";
import { InputInterface } from "../../../../../interface/InputInterface";
import { handleChangeInputGeneric } from "../../../../../functions/form";

let translations: any = getTranslations();

export type AcPrixVenInputState = {
  defaultValue: number;
  acCategorie: number | string;
};

type FormState = {
  acPrixVen: InputInterface;
  realAcPrixVen: InputInterface;
  valueLock: InputInterface;
};

interface ParentFormData {
  acCoef: number;
  arPrixAch: number;
}

export const AcPrixVenInput = React.forwardRef(
  ({ defaultValue, acCategorie }: AcPrixVenInputState, ref) => {
    const [parentFormData, setParentFormData] = React.useState<ParentFormData>({
      acCoef: 0,
      arPrixAch: 0,
    });
    const getExpectedAcPrixVen = () => {
      return Number(
        (parentFormData.arPrixAch * parentFormData.acCoef).toFixed(2),
      );
    };
    const [expectedAcPrixVen, setExpectedAcPrixVen] = React.useState(
      getExpectedAcPrixVen(),
    );

    const getDefaultValue = (): FormState => {
      const v = defaultValue ?? 0;
      return {
        acPrixVen: { value: v.toString() },
        realAcPrixVen: { value: v.toString() },
        valueLock: { value: v > 0 },
      };
    };
    const [values, setValues] = React.useState<FormState>(getDefaultValue());

    const handleChange =
      (prop: keyof FormState) =>
      (event: React.ChangeEvent<HTMLInputElement>) => {
        setValues((v) => {
          return {
            ...v,
            valueLock: {
              ...v.valueLock,
              value: true,
            },
          };
        });
        handleChangeInputGeneric(event, prop, setValues);
      };
    const handleRealAcPrixVen = () => {
      setValues((v) => {
        return {
          ...v,
          realAcPrixVen: {
            ...v.realAcPrixVen,
            value:
              v.valueLock.value &&
              expectedAcPrixVen !== Number(v.acPrixVen.value)
                ? v.acPrixVen.value
                : "0",
          },
        };
      });
    };

    const resetAcPrixVen = () => {
      const newValue = getExpectedAcPrixVen();
      setValues((v) => {
        return {
          ...v,
          acPrixVen: {
            ...v.acPrixVen,
            value: newValue.toString(),
          },
          valueLock: {
            ...v.valueLock,
            value: false,
          },
        };
      });
      setExpectedAcPrixVen(newValue);
    };

    useImperativeHandle(ref, () => ({
      onAcCoefChanged(data: number, thisAcCategorie: number | string) {
        if (acCategorie.toString() === thisAcCategorie.toString()) {
          setParentFormData((x) => {
            return {
              ...x,
              acCoef: data,
            };
          });
        }
      },
      onArPrixAchChanged(data: number) {
        setParentFormData((x) => {
          return {
            ...x,
            arPrixAch: data,
          };
        });
      },
    }));

    React.useEffect(() => {
      handleRealAcPrixVen();
    }, [expectedAcPrixVen, values.acPrixVen.value]); // eslint-disable-line react-hooks/exhaustive-deps

    React.useEffect(() => {
      resetAcPrixVen();
    }, [parentFormData]); // eslint-disable-line react-hooks/exhaustive-deps

    return (
      <>
        <label htmlFor={"_sage_acPrixVen"}>
          <Tooltip title={"acPrixVen"} arrow placement="top">
            <span>{translations["fArticles"]["acPrixVen"]}</span>
          </Tooltip>
        </label>
        <div style={{ display: "flex", alignItems: "flex-start" }}>
          <div style={{ position: "relative", flex: 1 }}>
            <input
              id={"_sage_fArtclients[" + acCategorie + "].acPrixVen"}
              name={"_sage_fArtclients[" + acCategorie + "].acPrixVen"}
              type={"hidden"}
              value={values.realAcPrixVen.value}
            />
            <input
              type={"number"}
              value={values.acPrixVen.value}
              onChange={handleChange("acPrixVen")}
              style={{ width: "100%" }}
              onBlur={() => {
                if (Number(values.acPrixVen.value) === 0) {
                  resetAcPrixVen();
                }
              }}
            />
            {values.acPrixVen.error && (
              <div className="sage_error_field">{values.acPrixVen.error}</div>
            )}
          </div>
          {Number(values.acPrixVen.value) !== expectedAcPrixVen &&
            Number(values.acPrixVen.value) > 0 && (
              <div style={{ position: "relative", top: "-2px" }}>
                <Tooltip title={translations.sentences.acPrixVenInput} arrow>
                  <IconButton>
                    <InfoIcon fontSize="small" />
                  </IconButton>
                </Tooltip>
              </div>
            )}
        </div>
      </>
    );
  },
);
